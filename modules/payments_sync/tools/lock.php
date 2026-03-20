<?php

class ProcessLock
{
    protected $lockFile;
    protected $maxLifetime;

    public function __construct($lockFile, $maxLifetime = 300)
    {
        $this->lockFile = $lockFile;
        $this->maxLifetime = $maxLifetime; // секунд (по умолчанию 5 минут)
    }

    /**
     * Попытка захватить lock
     */
    public function acquire()
    {
        if (file_exists($this->lockFile)) {
            $data = json_decode(@file_get_contents($this->lockFile), true);

            $createdAt = isset($data['time']) ? $data['time'] : 0;

            // если lock "свежий" — не даем запуск
            if ($createdAt && (time() - $createdAt < $this->maxLifetime)) {
                return [
                    'ok' => false,
                    'reason' => 'locked',
                    'age' => time() - $createdAt,
                ];
            }

            // если lock старый — считаем зависшим и перезаписываем
        }

        $payload = [
            'time' => time(),
            'pid' => getmypid(),
        ];

        file_put_contents($this->lockFile, json_encode($payload));

        return ['ok' => true];
    }

    /**
     * Освобождение lock
     */
    public function release()
    {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }
}