<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Container;
use App\Notifications\NotificationService;
use App\Repository\SettingRepository;

/**
 * La veste delle email: chi scrive (avvisi, promemoria, email ai clienti,
 * link per la password) manda testo semplice, qui diventa una email HTML
 * curata nei colori del gestionale (petrolio e ottone): logo in testa,
 * riquadro bianco con il testo, dati dell'agenzia in fondo.
 *
 * Il testo resta leggibile anche cosi' com'e' (versione solo testo, vedi
 * plainText()). Poche regole, tutte facoltative:
 *  - una riga vuota separa i paragrafi;
 *  - un blocco che inizia con "- " e' una scheda (gli avvisi): la prima
 *    riga e' il titolo, le righe sotto il testo;
 *  - una riga fatta solo da un indirizzo diventa il pulsante "Apri";
 *  - "[Testo del pulsante](https://...)" su una riga sua diventa un
 *    pulsante con quel testo;
 *  - gli indirizzi dentro il testo diventano link.
 * Tutto il testo si scrive come testo (htmlspecialchars), mai come HTML.
 */
final class EmailTemplate
{
    private const PETROL = '#12333f';
    private const BRASS = '#b99333';
    private const TEAL = '#1f5566';
    private const PAPER = '#f3f1ec';
    private const MUTED = '#5b6f77';

    private const BUTTON_LINE = '/^\s*\[([^\]]+)\]\((https?:\/\/\S+)\)\s*$/u';
    private const URL_LINE = '/^\s*(https?:\/\/\S+)\s*$/u';

    public function __construct(private readonly Container $container)
    {
    }

    /** La versione solo testo: i pulsanti "[Testo](url)" diventano "Testo: url". */
    public static function plainText(string $body): string
    {
        return (string) preg_replace('/^([ \t]*)\[([^\]]+)\]\((https?:\/\/\S+)\)[ \t]*$/mu', '$1$2: $3', $body);
    }

    public function render(string $subject, string $body): string
    {
        $brand = $this->brand();
        $blocks = preg_split('/\R\s*\R/u', trim(str_replace("\r\n", "\n", $body))) ?: [];

        $content = '';
        foreach ($blocks as $i => $block) {
            $content .= str_starts_with(ltrim($block), '- ')
                ? $this->card(ltrim($block))
                : $this->paragraph($block, $i === 0);
        }

        $preheader = self::e(mb_substr(trim((string) preg_replace('/\s+/u', ' ', self::plainText($body))), 0, 140));
        $head = $brand['logo'] !== null
            ? '<img src="' . self::e($brand['logo']) . '" alt="' . self::e($brand['name']) . '" height="44" style="display:block;height:44px;max-width:220px;border:0;outline:none;color:#12333f;font-size:20px;font-weight:bold;">'
            : '<span style="color:' . self::PETROL . ';font-size:22px;font-weight:bold;letter-spacing:-0.01em;">' . self::e($brand['name']) . '</span>';

        $contacts = array_filter([$brand['address'], $brand['phone'], $brand['email']]);
        $footer = '<strong style="color:' . self::PETROL . ';">' . self::e($brand['company']) . '</strong>'
            . ($contacts !== [] ? '<br>' . implode(' &middot; ', array_map([self::class, 'e'], $contacts)) : '')
            . ($brand['vat'] !== null ? '<br>P. IVA ' . self::e($brand['vat']) : '');

        return '<!doctype html><html lang="it"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="color-scheme" content="light"><title>' . self::e($subject) . '</title></head>'
            . '<body style="margin:0;padding:0;background:' . self::PAPER . ';">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $preheader . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . self::PAPER . ';">'
            . '<tr><td align="center" style="padding:28px 12px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;">'
            // Fascia petrolio e ottone (come il PDF del preventivo), poi il logo su bianco:
            // qualunque colore abbia il logo, si vede.
            . '<tr><td style="font-size:0;line-height:0;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td width="70%" style="background:' . self::PETROL . ';height:8px;border-radius:16px 0 0 0;">&nbsp;</td>'
            . '<td width="30%" style="background:' . self::BRASS . ';height:8px;border-radius:0 16px 0 0;">&nbsp;</td>'
            . '</tr></table></td></tr>'
            . '<tr><td style="background:#ffffff;padding:24px 32px 18px;border-bottom:1px solid #ebe6da;">' . $head . '</td></tr>'
            // Il testo.
            . '<tr><td style="background:#ffffff;padding:28px 32px 26px;border-radius:0 0 16px 16px;color:' . self::PETROL . ';font-size:15px;line-height:1.6;">'
            . $content
            . '</td></tr>'
            // Chi scrive.
            . '<tr><td style="padding:22px 32px 6px;color:' . self::MUTED . ';font-size:12px;line-height:1.6;text-align:center;">' . $footer . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /** Un paragrafo; il primo ("Ciao Sara,") un po' piu' in evidenza. */
    private function paragraph(string $block, bool $first): string
    {
        $out = '';
        $text = [];
        foreach (explode("\n", $block) as $line) {
            $button = $this->buttonOf($line);
            if ($button !== null) {
                $out .= $this->textP($text, $first) . $button;
                $text = [];
                continue;
            }
            $text[] = trim($line);
        }

        return $out . $this->textP($text, $first);
    }

    /** Una scheda: un avviso, con titolo, testo e pulsante. */
    private function card(string $block): string
    {
        $lines = explode("\n", substr($block, 2));
        $title = trim((string) array_shift($lines));
        $text = [];
        $buttons = '';
        foreach ($lines as $line) {
            $button = $this->buttonOf($line, true);
            if ($button !== null) {
                $buttons .= $button;
                continue;
            }
            $text[] = trim($line);
        }
        $text = array_values(array_filter($text, static fn (string $l) => $l !== ''));

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 14px;">'
            . '<tr><td style="background:#faf8f3;border:1px solid #ebe6da;border-left:4px solid ' . self::BRASS . ';border-radius:10px;padding:14px 18px;">'
            . '<div style="font-weight:bold;font-size:15px;color:' . self::PETROL . ';">' . self::linkify($title) . '</div>'
            . ($text !== [] ? '<div style="margin-top:4px;font-size:14px;color:' . self::MUTED . ';">' . implode('<br>', array_map([self::class, 'linkify'], $text)) . '</div>' : '')
            . $buttons
            . '</td></tr></table>';
    }

    private function buttonOf(string $line, bool $small = false): ?string
    {
        if (preg_match(self::BUTTON_LINE, $line, $m)) {
            return $this->button($m[1], $m[2], $small);
        }
        if (preg_match(self::URL_LINE, $line, $m)) {
            return $this->button('Apri', $m[1], $small);
        }

        return null;
    }

    private function button(string $label, string $url, bool $small): string
    {
        $pad = $small ? '8px 16px' : '12px 24px';
        $size = $small ? '13px' : '15px';
        $margin = $small ? '10px 0 0' : '6px 0 18px';

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:' . $margin . ';"><tr>'
            . '<td style="background:' . self::PETROL . ';border-radius:999px;">'
            . '<a href="' . self::e($url) . '" style="display:inline-block;padding:' . $pad . ';color:#ffffff;font-size:' . $size . ';font-weight:bold;text-decoration:none;border-radius:999px;">'
            . self::e($label) . ' &rarr;</a></td></tr></table>';
    }

    /** @param string[] $lines */
    private function textP(array $lines, bool $first): string
    {
        $lines = array_values(array_filter($lines, static fn (string $l) => $l !== ''));
        if ($lines === []) {
            return '';
        }
        $style = $first ? 'margin:0 0 16px;font-size:16px;font-weight:bold;' : 'margin:0 0 16px;';

        return '<p style="' . $style . '">' . implode('<br>', array_map([self::class, 'linkify'], $lines)) . '</p>';
    }

    private static function linkify(string $text): string
    {
        return (string) preg_replace(
            '#(https?://[^\s<]+[^\s<.,;:!?)\]])#u',
            '<a href="$1" style="color:' . self::TEAL . ';text-decoration:underline;">$1</a>',
            self::e($text)
        );
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    /** @return array{name:string,company:string,logo:?string,address:?string,phone:?string,email:?string,vat:?string} */
    private function brand(): array
    {
        $name = defined('APP_NAME') ? (string) APP_NAME : 'Il gestionale';
        $s = [];
        try {
            $settings = $this->container->get(SettingRepository::class);
            $s = $settings->tableExists() ? $settings->valuesByPrefix('project.') : [];
        } catch (\Throwable) {
            $s = [];
        }
        $v = static fn (string $k): ?string => trim((string) ($s[$k] ?? '')) !== '' ? trim((string) $s[$k]) : null;
        $name = $v('project.name') ?? $name;
        $logo = $v('project.logo');
        if ($logo !== null && !preg_match('#^https?://#', $logo)) {
            $logo = NotificationService::appUrl($this->container->get(Config::class)) . '/' . ltrim($logo, '/');
        }

        return [
            'name' => $name,
            'company' => $v('project.company') ?? $name,
            'logo' => $logo,
            'address' => $v('project.address'),
            'phone' => $v('project.phone'),
            'email' => $v('project.email'),
            'vat' => $v('project.vat'),
        ];
    }
}
