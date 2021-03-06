<?php

namespace Amp\SSH\Encryption;

use Amp\SSH\Encryption\CipherMode\Cbc;
use Amp\SSH\Encryption\CipherMode\CipherMode;
use Amp\SSH\Encryption\CipherMode\Ctr;

class Aes extends OpenSSL {
    private $keySize;

    private $cipherMode;

    public function __construct(int $keySize = 128, string $cipherMode = Ctr::NAME) {
        $this->keySize = $keySize;
        $this->cipherMode = $cipherMode;
    }

    public function getKeySize(): int {
        return $this->keySize / 8;
    }

    public function getBlockSize(): int {
        return 16;
    }

    public function getName(): string {
        return \sprintf('aes%s-%s', $this->keySize, $this->cipherMode);
    }

    protected function getOpenSSLMethod(): string {
        return \sprintf('aes-%s-%s', $this->keySize, $this->cipherMode);
    }

    protected function createCipherMode(string $iv): CipherMode {
        if ($this->cipherMode === Cbc::NAME) {
            return new Cbc($iv, $this->getOpenSSLMethod());
        }

        if ($this->cipherMode === Ctr::NAME) {
            return new Ctr($iv, $this->getOpenSSLMethod());
        }
    }

    public static function create() {
        return [
            new static(128, Cbc::NAME),
            new static(192, Cbc::NAME),
            new static(256, Cbc::NAME),
            new static(128, Ctr::NAME),
            new static(192, Ctr::NAME),
            new static(256, Ctr::NAME),
        ];
    }
}
