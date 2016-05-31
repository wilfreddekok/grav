<?php
namespace Grav\Common;

/**
 * The Response class handles Grav related tasks pertaining to processing
 * the HTTP response to the client
 *
 * Class Response
 * @package Grav\Common
 */
class Response
{
    const GZIP_COMPRESSION_LEVEL = 1;

    protected $body;

    public function getBody()
    {
        return $this->body;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function appendToBody($append)
    {
        if ($append) {
            $this->body .= $append;
        }
    }

    public function getProcessedBody()
    {
        if (Grav::instance()['config']->get('system.cache.gzip') && !ini_get('zlib.output_compression') && (ini_get('output_handler') != 'ob_gzhandler'))
        {
            return $this->compress();
        } else {
            header('Content-Encoding: none');
        }
        return $this->body;
    }

    public function compress()
    {
        $supported_encodings = [
            'x-gzip' => 'gz',
            'gzip' => 'gz',
            'deflate' => 'deflate'
        ];

        $client_encodings = array_intersect($this->getAcceptEncodings(), array_keys($supported_encodings));

        // check some prerequisites for compressing the page
        if (empty($client_encodings) || $this->haveHeadersBeenSent() || !$this->isConnectionAlive()) {
            return;
        }

        // loop over supported client encodings and compress if possible
        foreach($client_encodings as $encoding) {
            if ($supported_encodings[$encoding] === 'gz' || $supported_encodings[$encoding] === 'deflate') {

                // check for zlib extension.. continue if not loaded or output compression is not enabled
                if (!extension_loaded('zlib') || ini_get('zlib.output_compression'))
                {
                    continue;
                }

                // compress manually with gzencode
                $compressed_body = gzencode($this->getBody(), Response::GZIP_COMPRESSION_LEVEL, ($supported_encodings[$encoding] == 'gz') ? FORCE_GZIP : FORCE_DEFLATE);
                // if compression failed for whatever reason, continue to the next encoding
                if ($compressed_body === false)
                {
                    continue;
                }

                // Compression successful, we can set encoding, body and exit
                header('Content-Encoding: ' . $encoding);
                return $compressed_body;
                break;
            }
        }
        return $this->body;
    }

    public function setStandardHeaders()
    {
        $grav = Grav::instance();

        $extension = $grav['uri']->extension();

        /** @var Page $page */
        $page = $grav['page'];

        header('Content-type: ' . $this->mime($extension));

        // Calculate Expires Headers if set to > 0
        $expires = $page->expires();

        if ($expires > 0) {
            $expires_date = gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT';
            header('Cache-Control: max-age=' . $expires);
            header('Expires: ' . $expires_date);
        }

        // Set the last modified time
        if ($page->lastModified()) {
            $last_modified_date = gmdate('D, d M Y H:i:s', $page->modified()) . ' GMT';
            header('Last-Modified: ' . $last_modified_date);
        }

        // Calculate a Hash based on the raw file
        if ($page->eTag()) {
            header('ETag: ' . md5($page->raw() . $page->modified()));
        }

        // Set debugger data in headers
        if (!($extension === null || $extension == 'html')) {
            $grav['debugger']->enabled(false);
        }

        // Set HTTP response code
        if (isset($grav['page']->header()->http_response_code)) {
            http_response_code($grav['page']->header()->http_response_code);
        }

        // Vary: Accept-Encoding
        if ($grav['config']->get('system.pages.vary_accept_encoding', false)) {
            header('Vary: Accept-Encoding');
        }
    }

    /**
     * Returns mime type for the file format.
     *
     * @param string $format
     *
     * @return string
     */
    public function mime($format)
    {
        switch ($format) {
            case 'json':
                return 'application/json';
            case 'html':
                return 'text/html';
            case 'atom':
                return 'application/atom+xml';
            case 'rss':
                return 'application/rss+xml';
            case 'xml':
                return 'application/xml';
        }

        return 'text/html';
    }

    public static function haveHeadersBeenSent()
    {
        return headers_sent();
    }

    public static function isConnectionAlive()
    {
        return (connection_status() === CONNECTION_NORMAL);
    }

    public static function getAcceptEncodings()
    {
        $encodings = [];
        if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            $encodings = explode(',', strtolower(preg_replace("/\s+/", "", $_SERVER['HTTP_ACCEPT_ENCODING'])));
        }
        return $encodings;
    }


}
