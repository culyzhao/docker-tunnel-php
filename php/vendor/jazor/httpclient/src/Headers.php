<?php


namespace Jazor\Http;

class Headers
{
    private array $headers = [];
    private string $contentType = 'text/html';
    private ?string $contentEncoding = null;
    private int $contentLength = -1;
    private ?string $transferEncoding = null;

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return string|null
     */
    public function getContentEncoding(): ?string
    {
        return $this->contentEncoding;
    }

    /**
     * @return int
     */
    public function getContentLength(): int
    {
        return $this->contentLength;
    }

    /**
     * @return string|null
     */
    public function getTransferEncoding(): ?string
    {
        return $this->transferEncoding;
    }

    protected function prepareHeaders()
    {
        $header = $this->getSingletHeader('Content-Type');
        if ($header !== null) $this->contentType = $header;

        $header = $this->getSingletHeader('Content-Encoding');
        if ($header !== null) $this->contentEncoding = $header;

        $header = $this->getSingletHeader('Content-Length');
        if ($header !== null) $this->contentLength = intval($header);

        $header = $this->getSingletHeader('Transfer-Encoding');
        if ($header !== null) $this->transferEncoding = $header;
    }


    /**
     * @param string $line
     * @return $this
     */
    protected function addHeaderLine(string $line)
    {
        $idx = strpos($line, ':');
        if($idx === false) return $this;

        if($idx === strlen($line) -1){
            $this->addHeader(substr($line, 0, $idx), '');
            return $this;
        }
        $this->addHeader(substr($line, 0, $idx), trim(substr($line, $idx + 1)));
        return $this;
    }

    /**
     * @param string $name
     * @param string|null $value
     * @return $this
     */
    public function addHeader(string $name, ?string $value)
    {
        $name = ucwords($name, '-');
        if (!isset($this->headers[$name])) {
            $this->headers[$name] = $value === null ? [] : [$value];
            return $this;
        }
        if($value === null) return $this;
        $this->headers[$name][] = $value;
        return $this;
    }

    /**
     * @param string $name
     * @param string|null $value
     * @return $this
     */
    public function setHeader(string $name, ?string $value)
    {
        $name = ucwords($name, '-');
        $this->headers[$name] = $value === null ? [] : [$value];
        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function removeHeader(string $name)
    {
        $name = ucwords($name, '-');
        unset($this->headers[$name]);
        return $this;
    }

    /**
     * @param string $name
     * @return array|null
     */
    public function getHeader(string $name): ?array
    {
        $name = ucwords($name, '-');
        return $this->headers[$name] ?? null;
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getSingletHeader($name)
    {
        $name = ucwords($name, '-');
        if(!isset($this->headers[$name])) return null;
        return $this->headers[$name][0];
    }

    /**
     * @return string
     */
    public final function getAllHeaders(): string
    {
        return implode("\r\n", $this->getAllHeadersArray()) . "\r\n";
    }

    /**
     * @return array
     */
    public final function getAllHeadersArray(): array
    {
        $headers = $this->headers;
        $result = [];
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                if($value === null) continue;
                $result[] = sprintf('%s: %s', $name, $value);
            }
        }
        return $result;
    }
}
