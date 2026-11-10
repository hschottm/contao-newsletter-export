<?php

declare(strict_types=1);

namespace Hschottm\ContaoNewsletterExport\Backend;

use Contao\CoreBundle\Exception\ResponseException;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class BccExport
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function export(): never
    {
        $request = $this->requestStack->getCurrentRequest();
        $id = $request?->query->all()['id'] ?? null;

        if (!is_string($id) || !ctype_digit($id) || (int) $id < 1 || (int) $id > 4294967295) {
            throw new BadRequestHttpException('Invalid newsletter channel.');
        }

        throw new ResponseException($this->createResponse((int) $id));
    }

    public function createResponse(int $channelId): StreamedResponse
    {
        // Check the module AND the selected channel, including direct URL requests.
        if (!$this->authorizationChecker->isGranted('contao_user.modules.newsletter')
            || !$this->authorizationChecker->isGranted('contao_user.newsletters', $channelId)) {
            throw new AccessDeniedException('No access to this newsletter channel.');
        }

        if (!$this->connection->fetchOne('SELECT id FROM tl_newsletter_channel WHERE id = ?', [$channelId])) {
            throw new NotFoundHttpException('Newsletter channel not found.');
        }

        $response = new StreamedResponse(function () use ($channelId): void {
            $time = (int) (floor(time() / 60) * 60);
            $result = $this->connection->executeQuery(
                <<<'SQL'
                    SELECT DISTINCT r.email
                    FROM tl_newsletter_recipients r
                    WHERE r.pid = ? AND r.active = 1
                      AND NOT EXISTS (
                        SELECT 1 FROM tl_member m WHERE m.email = r.email
                        AND (m.disable = '1' OR (m.start != '' AND m.start > ?)
                             OR (m.stop != '' AND m.stop <= ?))
                      )
                      AND NOT EXISTS (
                        SELECT 1 FROM tl_newsletter_deny_list d
                        WHERE d.pid = r.pid AND d.hash = MD5(r.email)
                      )
                    ORDER BY r.email
                    SQL,
                [$channelId, $time, $time],
            );

            try {
                foreach ($result->iterateColumn() as $email) {
                    // Never allow malformed stored data to inject additional lines.
                    if (!is_string($email) || preg_match('/[\r\n\x00]/', $email)
                        || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }

                    echo $email."\r\n";
                }
            } finally {
                $result->free();
            }
        });

        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            sprintf('newsletter-%d-bcc.txt', $channelId),
        ));
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
