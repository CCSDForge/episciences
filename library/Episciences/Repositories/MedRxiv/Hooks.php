<?php



class Episciences_Repositories_MedRxiv_Hooks  extends Episciences_Repositories_BioMedRxiv

{
    public function __construct()
    {
        $this->setServer(self::AVAILABLE_SERVERS[Episciences_Repositories::MED_RXIV_ID]);
    }
}
