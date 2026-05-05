<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

abstract class BaseModel
{
    protected PDO $pdo;
    public function __construct()
    {
        $this->pdo = Database::connection();
    }
}

