<?php
require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Rfq.php';
require_once __DIR__ . '/../models/RfqItem.php';
require_once __DIR__ . '/../models/RfqSupplier.php';
require_once __DIR__ . '/../models/SupplierQuote.php';
require_once __DIR__ . '/../models/SupplierQuoteItem.php';

class ProcurementService {
    private $db;
    public function __construct($db) {
        $this->db = $db;
    }

    public function createRfq(array $rfqData, array $items): int {
        $this->db->beginTransaction();
        try {
            $rfq = new Rfq($this->db);
            $rfqId = $rfq->create($rfqData);
            $rfqItem = new RfqItem($this->db);
            foreach ($items as $it) {
                $rfqItem->create([
                    'rfq_id' => $rfqId,
                    'produto_id' => $it['produto_id'],
                    'quantidade' => $it['quantidade'],
                    'observacoes' => $it['observacoes'] ?? null
                ]);
            }
            $this->db->commit();
            return (int)$rfqId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function inviteSuppliers(int $rfqId, array $supplierIds): int {
        $rfqSup = new RfqSupplier($this->db);
        $count = 0;
        foreach ($supplierIds as $sid) {
            try {
                $rfqSup->create([
                    'rfq_id' => $rfqId,
                    'fornecedor_id' => $sid,
                    'status' => 'pendente'
                ]);
                $count++;
            } catch (Throwable $e) {
                // ignore duplicates due to UNIQUE(rfq_id, fornecedor_id)
            }
        }
        return $count;
    }

    public function submitSupplierQuote(int $rfqId, int $supplierId, array $items): int {
        $this->db->beginTransaction();
        try {
            $sq = new SupplierQuote($this->db);
            $existing = $sq->findOne(['rfq_id' => $rfqId, 'fornecedor_id' => $supplierId]);
            $quoteId = $existing ? (int)$existing['id'] : (int)$sq->create(['rfq_id' => $rfqId, 'fornecedor_id' => $supplierId]);
            // remove old items
            $this->db->prepare('DELETE FROM quote_items WHERE cotacao_id = :id')->execute([':id' => $quoteId]);
            $qi = new SupplierQuoteItem($this->db);
            foreach ($items as $it) {
                $qi->create([
                    'cotacao_id' => $quoteId,
                    'produto_id' => $it['produto_id'],
                    'preco_unitario' => $it['preco_unitario'],
                    'prazo_entrega' => $it['prazo_entrega'] ?? null,
                    'observacoes' => $it['observacoes'] ?? null
                ]);
            }
            // mark invite as responded
            $this->db->prepare('UPDATE rfq_suppliers SET status = \"respondeu\" WHERE rfq_id = :r AND fornecedor_id = :f')->execute([':r' => $rfqId, ':f' => $supplierId]);
            $this->db->commit();
            return $quoteId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function comparisonMatrix(int $rfqId): array {
        $products = $this->db->prepare('SELECT ri.produto_id, p.nome, p.unidade_medida, ri.quantidade FROM rfq_items ri JOIN products p ON p.id = ri.produto_id WHERE ri.rfq_id = :id');
        $products->execute([':id' => $rfqId]);
        $prods = $products->fetchAll();

        $suppliersStmt = $this->db->prepare('SELECT s.id, s.razao_social FROM rfq_suppliers rs JOIN suppliers s ON s.id = rs.fornecedor_id WHERE rs.rfq_id = :id');
        $suppliersStmt->execute([':id' => $rfqId]);
        $suppliers = $suppliersStmt->fetchAll();

        $pricesStmt = $this->db->prepare('SELECT q.fornecedor_id, qi.produto_id, qi.preco_unitario FROM supplier_quotes q JOIN quote_items qi ON qi.cotacao_id = q.id WHERE q.rfq_id = :id');
        $pricesStmt->execute([':id' => $rfqId]);
        $rows = $pricesStmt->fetchAll();

        $priceMap = [];
        foreach ($rows as $r) {
            $priceMap[$r['produto_id']][$r['fornecedor_id']] = (float)$r['preco_unitario'];
        }

        $matrix = [];
        $totals = [];
        foreach ($prods as $p) {
            $row = ['produto_id' => $p['produto_id'], 'nome' => $p['nome'], 'unidade' => $p['unidade_medida'], 'quantidade' => (float)$p['quantidade']];
            $min = null; $minSupplier = null;
            foreach ($suppliers as $s) {
                $price = $priceMap[$p['produto_id']][$s['id']] ?? null;
                $row['precos'][$s['id']] = $price;
                if ($price !== null && ($min === null || $price < $min)) {
                    $min = $price; $minSupplier = $s['id'];
                }
                if ($price !== null) {
                    $totals[$s['id']] = ($totals[$s['id']] ?? 0) + $price * (float)$p['quantidade'];
                }
            }
            $row['melhor_fornecedor'] = $minSupplier;
            $row['melhor_preco'] = $min;
            $matrix[] = $row;
        }

        $bestSupplier = null; $bestTotal = null;
        foreach ($totals as $sid => $total) {
            if ($bestTotal === null || $total < $bestTotal) {
                $bestTotal = $total; $bestSupplier = $sid;
            }
        }

        return [
            'suppliers' => $suppliers,
            'items' => $matrix,
            'totals' => $totals,
            'best_supplier' => $bestSupplier,
            'best_total' => $bestTotal
        ];
    }
}
