<?php
require_once __DIR__ . '/../classes/BaseModel.php';

class Supplier extends BaseModel {
    protected $table = 'suppliers';
    protected $primaryKey = 'id';
}
?>
