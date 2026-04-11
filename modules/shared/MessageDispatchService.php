<?php
/**
 * MessageDispatchService — відправляє повідомлення через канали з пріоритетом.
 *
 * Порядок за замовчуванням: Telegram → Viber → SMS → Email.
 * Зупиняється на першому успішному каналі.
 *
 * Використання:
 *   $result = MessageDispatchService::send($counterpartyId, $text);
 *   // або з кастомним пріоритетом:
 *   $result = MessageDispatchService::send($counterpartyId, $text, ['viber','sms']);
 *
 * Повертає:
 *   ['ok' => true,  'channel' => 'viber']
 *   ['ok' => false, 'channel' => null, 'error' => 'No available channel', 'tried' => [...]]
 */
class MessageDispatchService
{
    const DEFAULT_PRIORITY = array('telegram', 'viber', 'sms', 'email');

    // Whitelist фраз для згортання URL у клікабельний якір.
    // Використовується і в email (textToHtml), і в Telegram (textToTelegramHtml).
    // Фраза перед ":" + URL → <a href="URL">фраза</a>.
    const ANCHOR_REGEX =
        '#((?:Повна інформація[^:]*'
        . '|Деталі замовлення[^:]*'
        . '|Посилання[^:]*'
        . '|Інформація по замовленню[^:]*'
        . '|Інтерфейс покупця[^:]*'
        . '|Кабінет клієнта[^:]*'
        . '|Ваш кабінет[^:]*'
        . '|Сторінка замовлення[^:]*))[:\s]+(https?://\S+)#u';

    /**
     * Відправити повідомлення контрагенту.
     *
     * @param int    $counterpartyId
     * @param string $text
     * @param array  $priority   канали в порядку пріоритету (subset DEFAULT_PRIORITY)
     * @param bool   $alsoEmail  якщо true і email є — після основного каналу дублюємо на email
     * @param int    $orderId    опц.: прив'язати повідомлення до заказу у cp_messages.order_id
     * @return array ['ok'=>bool, 'channel'=>string|null, 'error'=>string|null, 'tried'=>array, 'email_copy'=>array|null]
     */
    public static function send($counterpartyId, $text, array $priority = null, $alsoEmail = false, $orderId = 0)
    {
        if ($priority === null) $priority = self::DEFAULT_PRIORITY;

        $contacts  = self::getContacts((int)$counterpartyId);
        $tried     = array();
        $lastError = 'No available channel';
        $mainOk    = false;
        $mainChan  = null;

        foreach ($priority as $channel) {
            $result = self::tryChannel($channel, $contacts, $text, (int)$counterpartyId, (int)$orderId);
            $tried[] = array('channel' => $channel, 'ok' => $result['ok'], 'error' => isset($result['error']) ? $result['error'] : null);
            if ($result['ok']) {
                $mainOk   = true;
                $mainChan = $channel;
                break;
            }
            $lastError = isset($result['error']) ? $result['error'] : $channel . ' failed';
        }

        $emailCopy = null;
        if ($alsoEmail && $mainOk && $mainChan !== 'email' && !empty($contacts['email'])) {
            $res = self::sendEmail($contacts, $text, (int)$counterpartyId, (int)$orderId);
            $emailCopy = array('ok' => $res['ok'], 'error' => isset($res['error']) ? $res['error'] : null);
        }

        if ($mainOk) {
            return array('ok' => true, 'channel' => $mainChan, 'tried' => $tried, 'email_copy' => $emailCopy);
        }
        return array('ok' => false, 'channel' => null, 'error' => $lastError, 'tried' => $tried, 'email_copy' => null);
    }

    /**
     * Завантажити контактні дані контрагента.
     */
    public static function getContacts($counterpartyId)
    {
        $cpId = (int)$counterpartyId;
        $r = Database::fetchRow('Papir',
            "SELECT c.telegram_chat_id,
                    COALESCE(cp.phone, cc.phone)   AS phone,
                    COALESCE(cp.email, cc.email)   AS email
             FROM counterparty c
             LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
             LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
             WHERE c.id = {$cpId} LIMIT 1"
        );
        if (!$r['ok'] || !$r['row']) {
            return array('phone' => null, 'email' => null, 'telegram_chat_id' => null);
        }
        $phone = $r['row']['phone'] ? AlphaSmsService::normalizePhone($r['row']['phone']) : null;
        return array(
            'phone'            => $phone ?: null,
            'email'            => $r['row']['email'] ?: null,
            'telegram_chat_id' => $r['row']['telegram_chat_id'] ?: null,
        );
    }

    // ── Канали ────────────────────────────────────────────────────────────────

    private static function tryChannel($channel, $contacts, $text, $counterpartyId, $orderId = 0)
    {
        switch ($channel) {
            case 'telegram': return self::sendTelegram($contacts, $text, $counterpartyId, $orderId);
            case 'viber':    return self::sendViber($contacts, $text, $counterpartyId, $orderId);
            case 'sms':      return self::sendSms($contacts, $text, $counterpartyId, $orderId);
            case 'email':    return self::sendEmail($contacts, $text, $counterpartyId, $orderId);
        }
        return array('ok' => false, 'error' => 'Unknown channel: ' . $channel);
    }

    private static function sendTelegram($contacts, $text, $counterpartyId, $orderId = 0)
    {
        if (empty($contacts['telegram_chat_id'])) {
            return array('ok' => false, 'error' => 'No Telegram chat_id');
        }

        // Якщо текст має whitelist-фразу перед URL → надсилаємо як HTML з анкором,
        // ховаючи URL. Інакше — plain text (як раніше).
        $html = self::textToTelegramHtml($text);
        if ($html !== null) {
            $res = TelegramBotService::sendMessageHtml($contacts['telegram_chat_id'], $html, true);
        } else {
            $res = TelegramBotService::sendMessage($contacts['telegram_chat_id'], $text);
        }
        if (!$res['ok']) {
            return array('ok' => false, 'error' => isset($res['error']) ? $res['error'] : 'Telegram error');
        }
        ChatRepository::saveMessage(array(
            'counterparty_id' => $counterpartyId,
            'channel'         => 'telegram',
            'direction'       => 'out',
            'is_auto'         => 1,
            'body'            => $text,
            'order_id'        => $orderId,
        ));
        return array('ok' => true);
    }

    /**
     * Конвертує plain-text у Telegram HTML, згортаючи URL після whitelist-фрази
     * у клікабельний <a> анкор. Повертає NULL якщо жодного згортання не відбулось —
     * тоді caller використовує plain sendMessage.
     *
     * Telegram HTML parse_mode: екрануємо <, >, & через htmlspecialchars;
     * підтримувані теги — <a>, <b>, <i>, <u>, <s>, <code>, <pre>.
     */
    private static function textToTelegramHtml($text)
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $found   = false;
        $result  = preg_replace_callback(
            self::ANCHOR_REGEX,
            function ($m) use (&$found) {
                $found = true;
                $anchor = rtrim($m[1]);
                $url    = $m[2];
                return '<a href="' . $url . '">' . $anchor . '</a>';
            },
            $escaped
        );
        return $found ? $result : null;
    }

    private static function sendViber($contacts, $text, $counterpartyId, $orderId = 0)
    {
        if (empty($contacts['phone'])) {
            return array('ok' => false, 'error' => 'No phone for Viber');
        }
        $res = AlphaSmsService::sendViber($contacts['phone'], $text);
        if (!$res || !$res['ok']) {
            $err = isset($res['error']) ? $res['error'] : 'Viber send failed';
            self::saveSystemMessage($counterpartyId, 'viber', $err);
            return array('ok' => false, 'error' => $err);
        }
        ChatRepository::saveMessage(array(
            'counterparty_id' => $counterpartyId,
            'channel'         => 'viber',
            'direction'       => 'out',
            'is_auto'         => 1,
            'body'            => $text,
            'phone'           => $contacts['phone'],
            'order_id'        => $orderId,
        ));
        return array('ok' => true);
    }

    private static function sendSms($contacts, $text, $counterpartyId, $orderId = 0)
    {
        if (empty($contacts['phone'])) {
            return array('ok' => false, 'error' => 'No phone for SMS');
        }
        $res = AlphaSmsService::sendSms($contacts['phone'], $text);
        if (!$res || !$res['ok']) {
            $err = isset($res['error']) ? $res['error'] : 'SMS send failed';
            self::saveSystemMessage($counterpartyId, 'sms', $err);
            return array('ok' => false, 'error' => $err);
        }
        ChatRepository::saveMessage(array(
            'counterparty_id' => $counterpartyId,
            'channel'         => 'sms',
            'direction'       => 'out',
            'is_auto'         => 1,
            'body'            => $text,
            'phone'           => $contacts['phone'],
            'order_id'        => $orderId,
        ));
        return array('ok' => true);
    }

    private static function sendEmail($contacts, $text, $counterpartyId, $orderId = 0)
    {
        if (empty($contacts['email'])) {
            return array('ok' => false, 'error' => 'No email address');
        }
        // Отримуємо ім'я контрагента для заголовка
        $rCp  = Database::fetchRow('Papir', "SELECT name FROM counterparty WHERE id={$counterpartyId} LIMIT 1");
        $name = ($rCp['ok'] && $rCp['row']) ? $rCp['row']['name'] : '';

        $html = self::textToHtml($text);

        $res = GmailSmtpService::send(
            $contacts['email'],
            $name,
            'Повідомлення від Papir CRM',
            $html,
            null, null,
            true // isHtml
        );
        if (!$res['ok']) return array('ok' => false, 'error' => $res['error']);
        ChatRepository::saveMessage(array(
            'counterparty_id' => $counterpartyId,
            'channel'         => 'email',
            'direction'       => 'out',
            'is_auto'         => 1,
            'body'            => $text,
            'email_addr'      => $contacts['email'],
            'order_id'        => $orderId,
        ));
        return array('ok' => true);
    }

    /**
     * Перетворює plain-text на простий HTML:
     *   • екранує спецсимволи
     *   • замінює URL на клікабельні <a>
     *     (якщо перед URL є "Повна інформація по замовленню №N:" — ховає URL під якорем)
     *   • переносить рядки через <br>
     */
    private static function textToHtml($text)
    {
        // Escape first, потім робимо URL клікабельними.
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Варіант A: фраза-підказка перед URL — ховаємо URL під клікабельний якір.
        // Використовуємо той самий whitelist що і в Telegram (self::ANCHOR_REGEX).
        $escaped = preg_replace_callback(
            self::ANCHOR_REGEX,
            function ($m) {
                $anchor = rtrim($m[1]);
                $url    = $m[2];
                return '<a href="' . $url . '" target="_blank" rel="noopener">' . $anchor . '</a>';
            },
            $escaped
        );

        // Решта голих URL — робимо клікабельними (сам URL як текст)
        $escaped = preg_replace(
            '#(?<![">])(https?://[^\s<]+)#',
            '<a href="$1" target="_blank" rel="noopener">$1</a>',
            $escaped
        );

        $html = nl2br($escaped, false);

        return '<!doctype html><html><body style="font-family:Arial,sans-serif;font-size:14px;color:#222;line-height:1.5;">'
             . $html
             . '</body></html>';
    }

    /**
     * Зберегти системне повідомлення про помилку доставки в чат.
     */
    private static function saveSystemMessage($counterpartyId, $channel, $error)
    {
        if ($counterpartyId <= 0) return;
        ChatRepository::saveMessage(array(
            'counterparty_id' => $counterpartyId,
            'channel'         => $channel,
            'direction'       => 'system',
            'status'          => 'failed',
            'body'            => '⚠ Повідомлення не доставлено: ' . $error,
        ));
    }
}
