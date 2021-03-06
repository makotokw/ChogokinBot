<?php

namespace Makotokw\TwientBot;

use Exception;
use Makotokw\Twient\Twitter\V1dot1 as Twitter;
use Makotokw\TwientBot\Storage\RedisStorage;
use Makotokw\TwientBot\Storage\Storage;
use Makotokw\TwientBot\Storage\TextFileStorage;
use Makotokw\TwientBot\Storage\XmlFileStorage;

class Bot
{
    /**
     * @var string
     */
    protected $oauthConsumerKey;

    /**
     * @var string
     */
    protected $oauthConsumerSecret;

    /**
     * @var string
     */
    protected $screenName;

    /**
     * @var string
     */
    protected $oauthUserToken;

    /**
     * @var string
     */
    protected $oauthUserSecret;

    /**
     * @var string
     */
    protected $dataDir;

    /**
     * @var string
     */
    protected $redisUrl;

    /**
     * @param string $oauthConsumerKey
     * @param string $oauthConsumerSecret
     */
    public function __construct($oauthConsumerKey, $oauthConsumerSecret)
    {
        $this->oauthConsumerKey = $oauthConsumerKey;
        $this->oauthConsumerSecret = $oauthConsumerSecret;
    }

    /**
     * @param string $dataDir
     */
    public function setDataDir($dataDir)
    {
        $this->dataDir = rtrim($dataDir, '/') . '/';
    }

    /**
     * @param string $redisUrl
     */
    public function setRedisUrl($redisUrl)
    {
        $this->redisUrl = $redisUrl;
    }

    /**
     * @param string $screenName
     * @param string $oauthUserToken
     * @param string $oauthUserSecret
     */
    public function setUser($screenName, $oauthUserToken, $oauthUserSecret)
    {
        $this->screenName = $screenName;
        $this->oauthUserToken = $oauthUserToken;
        $this->oauthUserSecret = $oauthUserSecret;
    }

    /**
     * @return array|bool
     */
    protected function createStorage()
    {
        $redisStorage = null;
        if ($this->redisUrl) {
            $redisStorage = new RedisStorage($this->redisUrl, strtolower($this->screenName) . '_messages');
            if (!$redisStorage->connect()) {
                unset($redisStorage);
            }
        }

        $baseFilename = $this->dataDir . strtolower($this->screenName);
        if (file_exists($baseFilename . '.xml')) {
            return [
                new XmlFileStorage($baseFilename . '.xml'),
                isset($redisStorage) ? $redisStorage : new XmlFileStorage($baseFilename . '.xml.cached')
            ];
        }
        $baseFilename = $this->dataDir . strtolower($this->screenName);
        if (file_exists($baseFilename . '.txt')) {
            return [
                new TextFileStorage($baseFilename . '.txt'),
                isset($redisStorage) ? $redisStorage : new TextFileStorage($baseFilename . '.txt.cached')
            ];
        }
        return false;
    }

    /**
     *
     */
    public function dumpCache()
    {
        /**
         * @var Storage $cacheStorage
         */
        list (, $cacheStorage) = $this->createStorage();
        if ($cacheStorage) {
            $cacheStorage->dump();
        }
    }

    /**
     *
     */
    public function clearCache()
    {
        /**
         * @var Storage $cacheStorage
         */
        list (, $cacheStorage) = $this->createStorage();
        if ($cacheStorage) {
            $cacheStorage->clear();
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function randomPost()
    {
        /**
         * @var Storage $originalStorage
         * @var Storage $cacheStorage
         */
        list ($originalStorage, $cacheStorage) = $this->createStorage();

        $messages = [];
        if ($cacheStorage) {
            $messages = $cacheStorage->read();
        }
        if (empty($messages)) {
            if ($originalStorage) {
                $messages = $originalStorage->read();
            }
            if (is_array($messages)) {
                shuffle($messages);
            }
        }

        if (empty($messages)) {
            throw new Exception(sprintf(
                'Load Error: %s%s.txt or .xml',
                $this->dataDir,
                strtolower($this->screenName)
            ));
        }

        do {
            if ($status = array_pop($messages)) {
                $status = trim($status);
            }
        } while (!is_null($status) && empty($status));

        if ($cacheStorage) {
            $cacheStorage->write($messages);
        }

        $twitter = new Twitter();
        $twitter->oAuth(
            $this->oauthConsumerKey,
            $this->oauthConsumerSecret,
            $this->oauthUserToken,
            $this->oauthUserSecret
        );
        return $twitter->statusesUpdate(compact('status'));
    }
}
