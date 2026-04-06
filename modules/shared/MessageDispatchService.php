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

    /**
     * Відправити повідомлення контрагенту.
     *
     * @param int    $counterpartyId
     * @param string $text
     * @param array  $priority  канали в порядку пріоритету (subset DEFAULT_PRIORITY)
     * @return array ['ok'=>bool, 'channel'=>string|null, 'error'=>string|null, 'tried'=>array]
     */
    public static function send($counterpartyId, $text, array $priority = null)
    {
        if ($priority === null) $priority = self::DEFAULT_PRIORITY;

        $contacts  = self::getContacts((int)$counterpartyId);
        $tried     = array();
        $lastError = 'No available channel';

        foreach ($priority as $channel) {
            $result = self::tryChannel($channel, $contacts, $text, (int)$counterpartyId);
            $tried[] = array('channel' => $channel, 'ok' => $result['ok'], 'error' => isset($result['error']) ? $result['error'] : null);
            if ($result['ok']) {
                return array('ok' => true, 'channel' => $channel, 'tried' => $tried);
            }
            $lastError = isset($result['error']) ? $result['error'] : $channel . ' failed';
        }

        return array('ok' => false, 'channel' => null, 'error' => $lastError, 'tried' => $tried);
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

    private static function tryChannel($channel, $contacts, $text, $counterpartyId)
    {
        switch ($channel) {
            case 'telegram': return self::sendTelegram($contacts, $text, $counterpartyId);
            case 'viber':    return self::sendViber($contacts, $text, $counterpartyId);
            case 'sms':      return self::sendSms($contacts, $text, $counterpartyId);
            case 'email':    return self::sendEmail($contacts, $text, $counterpartyId);
        }
        return array('ok' => false, 'error' => 'Unknown channel: ' . $channel);
    }

    private static function sendTelegram($contacts, $text, $counterpartyId)
    {
        if (empty($contacts['telegram_chat_id'])) {
            return array('ok' => false, 'error' => 'No Telegram chat_id');
        }
        $res = TelegramBotService::sendMessage($contacts['telegram_chat_id'], $text);
        if (!$res['ok']) {
            return array('ok' => false, 'error' => isset($res['error']) ? $res['error'] : 'Telegram error');
        }
        ChatRepository::saveMessage(array(
            'counterparty_id' => $counterpartyId,
            'channel'         => 'telegram',
            'direction'       => 'out',
            'is_auto'         => 1,
            'body'            => $text,
        ));
        return array('ok' => true);
    }

    private static function sendViber($contacts, $text, $counterpartyId)
    {
        if (empty($contacts['phone'])) {
            return array('ok' => false, 'error' => 'No phone for Viber');
        }
        $res = AlphaSmsService::sendViber($contacts['phone'], $text);
        if (!$res) return array('ok' => false, 'error' => 'Viber send failed');
        ChatRepository::saveMessage(array(
            'counterparty_id' => $counterpartyId,
            'channel'         => 'viber',
            'direction'       => 'out',
            'is_auto'         => 1,
            'body'            => $text,
            'phone'           => $contacts['phone'],
        ));
        return array('ok' => true);
    }

    private static function sendSms($contacts, $text, $counterpartyId)
    {
        if (empty($contacts['phone'])) {
            return array('ok' => false, 'error' => 'No phone for SMS');
        }
        AlphaSmsService::sendSms($contacts['phone'], $text);
        ChatRepository::saveMessage(array(
            'counterparty_id' => $counterpartyId,
            'channel'         => 'sms',
            'direction'       => 'out',
            'is_auto'         => 1,
            'body'            => $text,
            'phone'           => $contacts['phone'],
        ));
        return array('ok' => true);
    }

    private static function sendEmail($contacts, $text, $counterpartyId)
    {
        if (empty($contacts['email'])) {
            return array('ok' => false, 'error' => 'No email address');
        }
        // Отримуємо ім'я контрагента для заголовка
        $rCp  = Database::fetchRow('Papir', "SELECT name FROM counterparty WHERE id={$counterpartyId} LIMIT 1");
        $name = ($rCp['ok'] && $rCp['row']) ? $rCp['row']['name'] : '';

        $res = GmailSmtpService::send(
            $contacts['email'],
            $name,
            'Повідомлення від Papir CRM',
            $text
        );
        if (!$res['ok']) return array('ok' => false, 'error' => $res['error']);
        ChatRepository::saveMessage(array(
            'counterparty_id' => $counterpartyId,
            'channel'         => 'email',
            'direction'       => 'out',
            'is_auto'         => 1,
            'body'            => $text,
            'email_addr'      => $contacts['email'],
        ));
        return array('ok' => true);
    }
}
