<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../init.php";

class InvoiceUpdater {
    private $dbHost = 'localhost';
    private $dbPort = '';
    private $dbUsername = '';
    private $dbPassword = '';
    private $dbName = '';
    private $adminUsername = '';
    private $conn;

    public function __construct() {
        $this->conn = new mysqli($this->dbHost, $this->dbUsername, $this->dbPassword, $this->dbName);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function fetchAndProcessInvoices() {
        $invoices = $this->getUnpaidInvoices();

        if ($invoices !== null) {
            $i = 1;
            foreach ($invoices as $invoice) {
                $this->updateInvoiceItems($invoice);
                $i++;
            }
        } else {
            echo "Error fetching invoices.";
        }
    }

    private function getUnpaidInvoices() {
        $command = 'GetInvoices';
        $postData = array(
            'limitstart' => 0,
            'limitnum' => 150000,
            'orderby' => 'date',
            'status' => 'UnPaid',
            'sortorder' => 'DESC',
        );

        $results = localAPI($command, $postData, $this->adminUsername);

        if ($results['result'] == 'success') {
            return $results['invoices']['invoice'];
        }

        return null;
    }

    private function updateInvoiceItems($invoice) {
        $invoiceId = $invoice['id'];
        $currencyCode = $invoice['currencycode'];

        $command = 'GetInvoice';
        $postData = array('invoiceid' => $invoiceId);
        $invoiceDetails = localAPI($command, $postData, $this->adminUsername);

        if ($invoiceDetails['result'] == 'success') {
            foreach ($invoiceDetails['items']['item'] as &$item) {
                $description = $item['description'];

                if (strpos($description, 'Domain Renewal') !== false && strpos($description, 'Domain Renewal Fee') === false) {
                    $this->updateItemAmount($item, $currencyCode);
                }
            }
        } else {
            echo "Error fetching invoice details for invoice ID $invoiceId: " . $invoiceDetails['message'] . "<br/>";
        }
    }

    private function updateItemAmount(&$item, $currencyCode) {
        $description = $item['description'];

        if (strpos($description, '.xyz') !== false) {
            preg_match('~(\d+)\s+Year~i', $description, $matches);
            $numberOfYears = intval($matches[1]);

            if ($currencyCode == "USD") {
                $newItemAmount = number_format(14.30 * $numberOfYears, 2, '.', '');
            } else {
                $newItemAmount = number_format(1499.00 * $numberOfYears, 2, '.', '');
            }

            $item['amount'] = $newItemAmount;
            echo '' . $i++ . ') ' . $item['description'] . ' New Price ' . $newItemAmount . ' Payment Update Completed';
            echo "<br>";
            $invoice = $this->updateInvoiceItem($item['id'], $newItemAmount);

            if ($invoice !== null) {
                $invoiceID = $invoice['invoiceid'];
                $updatedAmount = $invoice['updated_amount'];
                $finalwork = $this->updateDomNewInvoice($invoiceID, $updatedAmount, $updatedAmount);
            }
        }
    }

    private function updateInvoiceItem($itemId, $amount) {
        $newAmount = $amount;
        $updateQuery = "UPDATE tblinvoiceitems SET amount = $newAmount WHERE id = $itemId";
        $updateResult = $this->conn->query($updateQuery);

        if ($updateResult) {
            $invoiceQuery = "SELECT invoiceid FROM tblinvoiceitems WHERE id = $itemId";
            $invoiceResult = $this->conn->query($invoiceQuery);

            if ($invoiceResult && $invoiceResult->num_rows > 0) {
                $invoiceRow = $invoiceResult->fetch_assoc();
                return array(
                    'invoiceid' => $invoiceRow['invoiceid'],
                    'updated_amount' => $this->getTotalAmountByInvoice($invoiceRow['invoiceid'])
                );
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    private function getTotalAmountByInvoice($invoiceId) {
        $query = "SELECT SUM(amount) AS total_amount FROM tblinvoiceitems WHERE invoiceid = $invoiceId";
        $result = $this->conn->query($query);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['total_amount'];
        }

        return 0;
    }

    private function updateDomNewInvoice($invoiceId, $newTotalAmount, $finalSubtotal) {
        $updateQuery = "UPDATE tblinvoices SET total = $newTotalAmount, subtotal = $finalSubtotal WHERE id = $invoiceId";
        $updateResult = $this->conn->query($updateQuery);
        return $updateResult;
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

$invoiceUpdater = new InvoiceUpdater();
$invoiceUpdater->fetchAndProcessInvoices();
?>
