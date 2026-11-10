# Contao Newsletter Export

![Contao Newsletter Export](logo.svg)

`hschottm/contao-newsletter-export` ergänzt die Newsletter-Empfängerverwaltung
von Contao um einen **BCC-Export** direkt nach **CSV-Import**.

## Verwendung

1. Im Backend **Newsletter** öffnen und die Abonnenten eines Verteilers anzeigen.
2. **BCC-Export** wählen.
3. Die heruntergeladene Datei `newsletter-<Verteiler-ID>-bcc.txt` öffnen und
   die Adressen in das BCC-Feld des extern erstellten Newsletters übernehmen.

Die UTF-8-Textdatei enthält eine E-Mail-Adresse pro Zeile (CRLF), ohne Kopfzeile,
BOM oder zusätzliche Trennzeichen. Die Adressen sind alphabetisch sortiert und
dedupliziert. Ein leerer Verteiler erzeugt eine leere Datei. Je nach Mailprogramm
müssen beim Einfügen die Zeilenumbrüche in dessen Adresstrennzeichen umgewandelt werden.

Exportiert werden alle **aktiven Abonnenten des gewählten Verteilers** unabhängig
von Suchfiltern, Sortierung und Seitennavigation im Backend. Nicht aktivierte
Abonnements, Einträge auf der Sperrliste des Verteilers und Adressen deaktivierter,
noch nicht freigeschalteter oder abgelaufener Mitglieder werden ausgeschlossen.
Ungültige E-Mail-Adressen werden übersprungen. Internationalisierte Domainnamen
bleiben in ihrer von Contao gespeicherten ASCII-/Punycode-Schreibweise erhalten.

Der Export verlangt sowohl Zugriff auf das Newsletter-Modul als auch auf den
gewählten Verteiler. Er verändert keine Abonnements, versendet keine Nachrichten
und speichert keine Exportdatei auf dem Server. Der Download darf nicht im Cache
abgelegt werden.

## Installation

Voraussetzungen: **Contao 5.3 oder neuer innerhalb von Contao 5**, das offizielle
Newsletter-Bundle und PHP 8.1 oder neuer (zusätzlich gelten die PHP-Anforderungen
der eingesetzten Contao-Version).

Das Paket ist auf Packagist veröffentlicht und kann über den Contao Manager
oder Composer installiert werden:

```bash
composer require hschottm/contao-newsletter-export:^1.0
```

Danach den Contao-Anwendungscache neu aufbauen und öffentliche Bundle-Assets
installieren, sofern dies nicht bereits durch die Composer-Skripte geschieht:

```bash
php vendor/bin/contao-console cache:clear --env=prod
php vendor/bin/contao-console assets:install public
```

Das Bundle wird automatisch über das Contao-Manager-Plugin registriert.
Es sind keine Datenbankänderungen erforderlich.

## Übersetzungen

Button und Tooltip sind für sämtliche 20 Sprachcodes übersetzt, die in den
offiziellen, lokal geprüften Contao-5.3-Bundles enthalten sind:
`cs`, `de`, `en`, `es`, `fa`, `fr`, `it`, `ja`, `ko`, `lv`, `nl`, `pl`, `pt`,
`ru`, `sk`, `sl`, `sq`, `sr`, `sv`, `tr`. Englisch dient als Fallback.

## Paket-Icon

[logo.svg](logo.svg) enthält das skalierbare Paket-Icon: eine anthrazitfarbene
Grundform mit orangefarbener Ecke sowie einem weißen Briefumschlag mit Export-Pfeil.
Die Grundform und der Farbakzent bilden die gemeinsame Gestaltung der
Erweiterungsfamilie. Das SVG enthält keine eingebetteten Rasterbilder oder externen Ressourcen.

Für die Anzeige im Contao Manager muss das Icon zusätzlich im zentralen Repository
[contao/package-metadata](https://github.com/contao/package-metadata) unter
`meta/hschottm/contao-newsletter-export/logo.svg` eingereicht werden.
Die Datei im Composer-Paket allein registriert noch kein Manager-Logo.
Siehe die [Metadata-Dokumentation](https://contao.github.io/package-metadata/docs/en/).

## Entwicklung

Der Hauptbranch ist `main`, der zusätzliche Kompatibilitätsbranch heißt `contao53`.
Beide enthalten zum Release `1.0.1` denselben Stand.

```bash
composer install
composer test
```

Die Tests verwenden eine isolierte SQLite-Datenbank mit synthetischen Adressen;
benötigt werden `pdo_sqlite` und die üblichen Contao-PHP-Erweiterungen. Sie prüfen
Exportformat, Verteilerauswahl, Aktivierung, Mitgliederstatus, Sperrliste,
Zugriffsrechte, fehlerhafte Anfragen, leere Listen und Übersetzungsabdeckung.

## Lizenz

Copyright (c) 2026 Helmut Schottmüller.

GNU Lesser General Public License, Version 3 oder neuer
(**LGPL-3.0-or-later**). Siehe [LICENSE](LICENSE) und [COPYING](COPYING).
