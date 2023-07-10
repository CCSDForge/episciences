<?php


class Episciences_Repositories_BioRxiv_Hooks extends Episciences_Repositories_BioMedRxiv

{
    public function  __construct()
    {
        $this->setServer(
            self::AVAILABLE_SERVERS[Episciences_Repositories::BIO_RXIV_ID ]
        );
    }

}