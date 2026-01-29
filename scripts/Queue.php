<?php
namespace scripts;

use Episciences\QueueMessage;

require_once "AbstractScript.php";

abstract class Queue extends AbstractScript
{
    public const CLIENT_TIMEOUT = 30;
    protected string $type;
    public function __construct()
    {
        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), ['delProcessed|dp' => "delete processed"]));
        parent::__construct();
        $this->initLogging();
    }


    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getQueueMessages(): array
    {
        return (new QueueMessage(['type' => $this->getType()]))->receive();
    }


    public function init(): void
    {
        defineSQLTableConstants();
        defineSimpleConstants();
        defineApplicationConstants();
        $this->initApp();
        $this->initDb();
    }
}













































































