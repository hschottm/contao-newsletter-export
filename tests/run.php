<?php

declare(strict_types=1);

use Contao\CoreBundle\Exception\ResponseException;
use Doctrine\DBAL\DriverManager;
use Hschottm\ContaoNewsletterExport\Backend\BccExport;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require getenv('TEST_VENDOR_AUTOLOAD') ?: __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../src/Backend/BccExport.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function throws(string $type, callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        check($e instanceof $type, 'Expected '.$type.', got '.$e::class.': '.$e->getMessage());
        return;
    }
    throw new RuntimeException('Expected '.$type);
}
function body(BccExport $export, int $id): string
{
    $response = $export->createResponse($id);
    ob_start();
    try {
        $response->sendContent();
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

$db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$db->getNativeConnection()->sqliteCreateFunction('MD5', md5(...), 1);
$db->executeStatement('CREATE TABLE tl_newsletter_channel (id INTEGER PRIMARY KEY)');
$db->executeStatement('CREATE TABLE tl_newsletter_recipients (pid INTEGER, email TEXT, active INTEGER)');
$db->executeStatement("CREATE TABLE tl_member (email TEXT, disable TEXT DEFAULT '', start TEXT DEFAULT '', stop TEXT DEFAULT '')");
$db->executeStatement('CREATE TABLE tl_newsletter_deny_list (pid INTEGER, hash TEXT)');
foreach ([1, 2, 3] as $id) {
    $db->insert('tl_newsletter_channel', ['id' => $id]);
}
foreach ([
    [1, 'zeta@example.org', 1], [1, 'alpha@example.org', 1],
    [1, 'alpha@example.org', 1], [1, 'inactive@example.org', 0],
    [2, 'other-channel@example.org', 1], [1, 'disabled@example.org', 1],
    [1, 'future@example.org', 1], [1, 'expired@example.org', 1],
    [1, 'blocked@example.org', 1], [1, 'valid-member@example.org', 1],
    [1, "bad@example.org\r\ninjected@example.org", 1], [1, 'not-an-email', 1],
    [1, 'info@xn--bcher-kva.de', 1], [1, 'start-now@example.org', 1],
    [1, 'stop-now@example.org', 1],
] as [$pid, $email, $active]) {
    $db->insert('tl_newsletter_recipients', compact('pid', 'email', 'active'));
}
$now = (int) (floor(time() / 60) * 60);
foreach ([
    ['email' => 'disabled@example.org', 'disable' => '1'],
    ['email' => 'future@example.org', 'start' => (string) ($now + 3600)],
    ['email' => 'expired@example.org', 'stop' => (string) ($now - 3600)],
    ['email' => 'valid-member@example.org', 'start' => (string) ($now - 3600), 'stop' => (string) ($now + 3600)],
    ['email' => 'start-now@example.org', 'start' => (string) $now],
    ['email' => 'stop-now@example.org', 'stop' => (string) $now],
] as $member) {
    $db->insert('tl_member', $member);
}
$db->insert('tl_newsletter_deny_list', ['pid' => 1, 'hash' => md5('blocked@example.org')]);
$db->insert('tl_newsletter_deny_list', ['pid' => 2, 'hash' => md5('alpha@example.org')]);

$security = new class implements AuthorizationCheckerInterface {
    public bool $module = true;
    public array $channels = [1, 2, 3, 99];
    public function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        return match ($attribute) {
            'contao_user.modules.newsletter' => $this->module,
            'contao_user.newsletters' => in_array($subject, $this->channels, true),
            default => false,
        };
    }
};
$stack = new RequestStack();
$export = new BccExport($db, $security, $stack);
check(body($export, 1) === "alpha@example.org\r\ninfo@xn--bcher-kva.de\r\nstart-now@example.org\r\nvalid-member@example.org\r\nzeta@example.org\r\n", 'Eligible addresses, deduplication, ordering and CRLF');
check(body($export, 2) === "other-channel@example.org\r\n", 'Selected channel isolation');
check(body($export, 3) === '', 'Empty channel');
$response = $export->createResponse(1);
check($response->headers->get('Content-Type') === 'text/plain; charset=UTF-8', 'Plain UTF-8 text');
check(str_contains($response->headers->get('Content-Disposition'), 'attachment; filename=newsletter-1-bcc.txt'), 'Download name');
check($response->headers->hasCacheControlDirective('no-store'), 'Do not cache addresses');
$security->module = false;
throws(AccessDeniedException::class, fn () => $export->createResponse(1));
$security->module = true;
$security->channels = [2, 3, 99];
throws(AccessDeniedException::class, fn () => $export->createResponse(1));
$security->channels[] = 1;
throws(NotFoundHttpException::class, fn () => $export->createResponse(99));
foreach ([null, '', '-1', '0', '1 OR 1=1', '1.5', '4294967296', ['1']] as $id) {
    $stack->push(new Request(['id' => $id]));
    throws(BadRequestHttpException::class, fn () => $export->export());
    $stack->pop();
}
$stack->push(new Request(['id' => '1', 'search' => 'does-not-exist', 'limit' => '0,1']));
try {
    $export->export();
    throw new RuntimeException('Missing download response');
} catch (ResponseException $e) {
    ob_start();
    $e->getResponse()->sendContent();
    $text = ob_get_clean();
    check($text === body($export, 1), 'Backend dispatch ignores search and pagination');
}
$stack->pop();

$GLOBALS['TL_DCA']['tl_newsletter_recipients']['list']['global_operations'] = ['before' => [], 'import' => [], 'after' => []];
require __DIR__.'/../contao/dca/tl_newsletter_recipients.php';
check(array_keys($GLOBALS['TL_DCA']['tl_newsletter_recipients']['list']['global_operations']) === ['before', 'import', 'bccExport', 'after'], 'Insert directly after import');
$locales = explode(' ', 'cs de en es fa fr it ja ko lv nl pl pt ru sk sl sq sr sv tr');
foreach ($locales as $locale) {
    require __DIR__.'/../contao/languages/'.$locale.'/tl_newsletter_recipients.php';
    $translation = $GLOBALS['TL_LANG']['tl_newsletter_recipients']['bccExport'];
    check(count($translation) === 2 && $translation[0] !== '' && $translation[1] !== '', 'Label and tooltip: '.$locale);
}
check((int) $db->fetchOne('SELECT COUNT(*) FROM tl_newsletter_recipients') === 15, 'Export does not modify subscriptions');
// Verify the actual bundle paths and public service wiring used by Contao.
require_once __DIR__.'/../src/ContaoNewsletterExportBundle.php';
$bundle = new Hschottm\ContaoNewsletterExport\ContaoNewsletterExportBundle();
check(is_file($bundle->getPath().'/contao/config/config.php'), 'Discover Contao configuration');
check(is_file($bundle->getPath().'/public/export.svg'), 'Discover public icon');
$container = new Symfony\Component\DependencyInjection\ContainerBuilder();
foreach (['database_connection', 'security.authorization_checker', 'request_stack'] as $id) {
    $container->register($id)->setSynthetic(true)->setPublic(true);
}
$loader = new Symfony\Component\DependencyInjection\Loader\YamlFileLoader(
    $container,
    new Symfony\Component\Config\FileLocator(__DIR__.'/../config'),
);
$loader->load('services.yaml');
$container->compile();
$container->set('database_connection', $db);
$container->set('security.authorization_checker', $security);
$container->set('request_stack', $stack);
check($container->get(BccExport::class) instanceof BccExport, 'Public backend callback resolves from container');
require __DIR__.'/../contao/config/config.php';
check($GLOBALS['BE_MOD']['content']['newsletter']['bccExport'] === [BccExport::class, 'export'], 'Backend action registered');
echo sprintf("OK (%d checks)\n", $checks);
