<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

abstract class Migration
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    abstract public function up(): void;

    abstract public function down(): void;
}
