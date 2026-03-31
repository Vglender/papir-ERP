<?php
/**
 * Avatar helper.
 * Avatar stored in settings_json->avatar:
 *   null / empty      → default blue gradient + initials
 *   "color:blue"      → named gradient + initials
 *   "emoji:🦊"        → emoji on blue gradient
 *   "emoji:🦊:teal"   → emoji on teal gradient
 *   "image/..."       → photo
 */

function papirAvatarGradients() {
    return array(
        'blue'   => 'linear-gradient(135deg,#5b8af8,#7c3aed)',
        'green'  => 'linear-gradient(135deg,#10b981,#059669)',
        'orange' => 'linear-gradient(135deg,#f97316,#ea580c)',
        'red'    => 'linear-gradient(135deg,#ef4444,#dc2626)',
        'pink'   => 'linear-gradient(135deg,#ec4899,#db2777)',
        'indigo' => 'linear-gradient(135deg,#6366f1,#4f46e5)',
        'teal'   => 'linear-gradient(135deg,#14b8a6,#0d9488)',
        'gray'   => 'linear-gradient(135deg,#6b7280,#4b5563)',
        'amber'  => 'linear-gradient(135deg,#fbbf24,#f59e0b)',
        'cyan'   => 'linear-gradient(135deg,#06b6d4,#0891b2)',
    );
}

function papirAvatarEmojis() {
    return array(
        '🦊','🐱','🐶','🦁','🐻','🦝','🐼','🦄',
        '🦅','🐺','🐯','🦋','😎','🤖','🧙','🦸',
        '👾','🌟','⚡','🔥','💎','🚀','🌿','🎯',
    );
}

/**
 * Parses avatar value into structured info.
 * Returns:
 *   type    — 'color' | 'emoji' | 'image'
 *   bg      — CSS background string
 *   emoji   — emoji char (only for type=emoji, else '')
 *   isImage — bool
 *   bgKey   — color key name (for picker highlight)
 */
function papirAvatarInfo($avatarVal)
{
    $grads = papirAvatarGradients();

    if (!$avatarVal) {
        return array('type'=>'color','bg'=>$grads['blue'],'emoji'=>'','isImage'=>false,'bgKey'=>'blue');
    }

    if (strpos($avatarVal, 'image/') === 0) {
        return array('type'=>'image','bg'=>'none','emoji'=>'','isImage'=>true,'bgKey'=>'blue',
                     'url'=>'/' . $avatarVal);
    }

    if (strpos($avatarVal, 'emoji:') === 0) {
        // format: emoji:CHAR or emoji:CHAR:colorkey
        $rest   = substr($avatarVal, 6); // "🦊" or "🦊:teal"
        $colPos = mb_strrpos($rest, ':', 0, 'UTF-8');
        $emoji  = $rest;
        $bgKey  = 'blue';
        if ($colPos !== false) {
            $maybeKey = mb_substr($rest, $colPos + 1, null, 'UTF-8');
            if (isset($grads[$maybeKey])) {
                $emoji = mb_substr($rest, 0, $colPos, 'UTF-8');
                $bgKey = $maybeKey;
            }
        }
        return array('type'=>'emoji','bg'=>$grads[$bgKey],'emoji'=>$emoji,'isImage'=>false,'bgKey'=>$bgKey);
    }

    if (strpos($avatarVal, 'color:') === 0) {
        $key  = substr($avatarVal, 6);
        $grad = isset($grads[$key]) ? $grads[$key] : $grads['blue'];
        return array('type'=>'color','bg'=>$grad,'emoji'=>'','isImage'=>false,'bgKey'=>$key);
    }

    return array('type'=>'color','bg'=>$grads['blue'],'emoji'=>'','isImage'=>false,'bgKey'=>'blue');
}

/**
 * Reads avatar value from settings row.
 */
function papirAvatarFromSettings($settings)
{
    if (!$settings) { return null; }
    $json = isset($settings['settings_json']) ? $settings['settings_json'] : null;
    if ($json) {
        $parsed = json_decode($json, true);
        if (is_array($parsed) && !empty($parsed['avatar'])) {
            return $parsed['avatar'];
        }
    }
    return null;
}