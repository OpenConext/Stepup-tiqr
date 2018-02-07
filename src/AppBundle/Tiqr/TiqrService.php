<?php

namespace AppBundle\Tiqr;

/**
 * Wrapper around the legacy Tiqr service.
 */
class TiqrService
{
    /**
     * @var \Tiqr_Service
     */
    public $tiqrService;
    /**
     * @var \Tiqr_UserStorage_Interface
     */
    public $storage;
    public $options;

    public function __construct($tiqrService = null, $storage, $option)
    {
        $this->tiqrService = $tiqrService;
        $this->storage = $storage;
        $this->options = $option;
    }

    public function startEnrollmentSession($userName)
    {
        return $this->tiqrService->startEnrollmentSession($this->generateId(), $userName);
    }

    private function generateId($length = 4)
    {
        return base_convert(time(), 10, 36).'-'.base_convert(mt_rand(0, pow(36, $length)), 10, 36);
    }

    public function generateEnrollmentQR($metadataURL)
    {
        $this->tiqrService->generateEnrollmentQR($metadataURL);
    }

}
