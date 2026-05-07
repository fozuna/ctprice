<?php
require_once __DIR__ . '/../classes/BaseModel.php';

class Product extends BaseModel {
    protected $table = 'products';
    protected $primaryKey = 'id';
}
?>
