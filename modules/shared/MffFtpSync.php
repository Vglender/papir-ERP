<?php
/**
 * MffFtpSync — синхронизирует файлы изображений на удалённый mff-сервер через FTP.
 *
 * mff-сайт (menufolder.com.ua) находится на отдельном сервере.
 * Все файлы изображений физически хранятся на off-сервере (officetorg.com.ua).
 * Этот класс обеспечивает зеркалирование файлов на mff через FTP.
 *
 * Remote image root: /home/menufold/menufolder.com.ua/www/image/
 * Local image root:  /var/www/menufold/data/www/officetorg.com.ua/image/
 */
class MffFtpSync
{
    private $host     = 'menufold.ftp.tools';
    private $port     = 21;
    private $user     = 'menufold_vas';
    private $pass     = 'oM6nkX7zrD0iyM1bfE5b';
    private $remoteBase = '/menufolder.com.ua/www/image/';
    private $localBase  = '/var/www/menufold/data/www/officetorg.com.ua/image/';

    private $conn = null;

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Upload a file to mff.
     * $relPath — relative path inside image dir, e.g. "catalog/product/84/84/file.jpg"
     */
    public function upload($relPath)
    {
        $relPath   = ltrim($relPath, '/');
        $localFile = $this->localBase . $relPath;
        if (!file_exists($localFile)) return false;

        if (!$this->connect()) return false;

        $remotePath = $this->remoteBase . $relPath;
        $this->mkdirRecursive(dirname($remotePath));

        $result = ftp_put($this->conn, $remotePath, $localFile, FTP_BINARY);
        return $result;
    }

    /**
     * Delete a file from mff.
     */
    public function delete($relPath)
    {
        $relPath = ltrim($relPath, '/');
        if (!$this->connect()) return false;

        $remotePath = $this->remoteBase . $relPath;
        @ftp_delete($this->conn, $remotePath);
        return true;
    }

    public function disconnect()
    {
        if ($this->conn) {
            ftp_close($this->conn);
            $this->conn = null;
        }
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function connect()
    {
        if ($this->conn) return true;

        $conn = @ftp_connect($this->host, $this->port, 15);
        if (!$conn) return false;

        if (!@ftp_login($conn, $this->user, $this->pass)) {
            ftp_close($conn);
            return false;
        }

        ftp_pasv($conn, true);
        $this->conn = $conn;
        return true;
    }

    private function mkdirRecursive($remotePath)
    {
        // Build list of dirs to create from remoteBase down
        $rel = substr($remotePath, strlen($this->remoteBase));
        if ($rel === false || $rel === '') return;

        $parts   = explode('/', trim($rel, '/'));
        $current = rtrim($this->remoteBase, '/');

        foreach ($parts as $part) {
            if ($part === '') continue;
            $current .= '/' . $part;
            @ftp_mkdir($this->conn, $current);
        }
    }
}
