<?php
require_once __DIR__ . '/../classes/BaseModel.php';

class SupplierQuoteItem extends BaseModel {
    protected $table = 'quote_items';
    protected $primaryKey = 'id';
}
?>
