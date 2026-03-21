<?php

require_once '/var/www/papir/modules/database/database.php';
require_once '/var/sqript/Utilits/simplexlsx.class.php';
require_once ('/var/www/menufold/data/www/officetorg.com.ua/PI/exel/xlsxwriter.class.php');
require_once '/var/sqript/Merchant/Merchant.php';


		use Google\Client;
		use Google\Service\ShoppingContent;
		use Google\Service\ShoppingContent\Product;
		use Google\Service\ShoppingContent\Price;
		use Google\Service\ShoppingContent\ProductsCustomBatchRequest;
		use Google\Service\ShoppingContent\ProductsCustomBatchRequestEntry;
		use Google\Service\ShoppingContent\SearchRequest;
		use Google\Service\ShoppingContent\ReportRow;
		
	if (!function_exists('logLine')) {
		function logLine($text, $type = 'info') {
			echo '<div class="log-line log-' . $type . '">'
				. htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
				. '</div>';

			if (function_exists('ob_flush')) {
				@ob_flush();
			}

			flush();
		}
	}

	if (!function_exists('logInfo')) {
		function logInfo($text) {
			logLine($text, 'info');
		}
	}

	if (!function_exists('logProgress')) {
		function logProgress($text) {
			logLine($text, 'progress');
		}
	}

	if (!function_exists('logSuccess')) {
		function logSuccess($text) {
			logLine($text, 'success');
		}
	}

	if (!function_exists('logError')) {
		function logError($text) {
			logLine($text, 'error');
		}
	}	

	$data = GetDataFromDb();

	$result = [];
	if ($data !== null) {
		$result = applyStockLogic($data);
	}
	
	echo PHP_EOL
		. 'Без акції: ' . (isset($result['stock_without_action']) ? count($result['stock_without_action']) : 0) . PHP_EOL
		. 'В залишках: ' . (isset($result['codes']) ? count($result['codes']) : 0) . PHP_EOL;
	
	$writer1 = new XLSXWriter();

	$header1 = array(
		'product_id' => 'integer',
		'name'       => 'string',
		'stock'      => 'integer'
	);

	$writer1->writeSheetHeader('Sheet1', $header1);

	foreach ($result['stock_without_action'] as $row) {
		$writer1->writeSheetRow('Sheet1', array(
			(int)$row['product_id'],
			(string)$row['name'],
			(int)$row['stock']
		));
	}
	
	logProgress('Updating Special table...');
	UpdSpecial($result['codes']);
	logSuccess('Special table updated.');

	logProgress('Updating specials on site and Merchant...');
	correctSpecial($result['codes'], $result['product_stock']);
	logSuccess('Site specials update finished.');

	logProgress('Updating product quantities...');
	updateProductQuantityFromMS();
	logSuccess('Product quantities updated.');

	$writer1->writeToFile('/var/sqript/products/without_action.xlsx');

	logSuccess('without_action.xlsx created');
	
	$writerCodes = new XLSXWriter();

	$headerCodes = array(
		'product_id'    => 'integer',
		'discount'      => 'integer',
		'super_discont' => 'integer'
	);

	$writerCodes->writeSheetHeader('Sheet1', $headerCodes);

	foreach ($result['codes'] as $row) {

		$writerCodes->writeSheetRow('Sheet1', array(
			(int)$row['product_id'],
			(int)$row['discount'],
			(int)$row['super_discont']
		));
	}

	$writerCodes->writeToFile('/var/sqript/products/report_stock.xlsx');

	logSuccess('report_stock.xlsx created');
	logInfo('=== END SITE UPDATE ===');
	
	
	
function GetDataFromDb() {

    $data = array(
        'codes' => array(),
        'product_stock' => array()
    );

    $mysqli = connectbd('ms');

    // Акции
    $res = $mysqli->query("SELECT product_id, discount, super_discont FROM `action`");
    while ($row = $res->fetch_assoc()) {
        $data['codes'][] = array(
            'product_id' => (int)$row['product_id'],
            'discount' => (int)$row['discount'],
            'super_discont' => (int)$row['super_discont']
        );
    }

    // Остатки + виртуальный остаток + name
    $sqlStock = "SELECT
                    CAST(s.model AS UNSIGNED) AS product_id,
                    s.name,
                    (s.stock + COALESCE(v.stock, 0)) AS stock
                 FROM `stock_` s
                 LEFT JOIN `virtual` v
                    ON v.product_id = CAST(s.model AS UNSIGNED)
                 WHERE CAST(s.model AS UNSIGNED) > 0";

    $res2 = $mysqli->query($sqlStock);
    while ($row = $res2->fetch_assoc()) {
        $data['product_stock'][] = array(
            'product_id' => (int)$row['product_id'],
            'name' => isset($row['name']) ? $row['name'] : '',
            'stock' => (int)$row['stock']
        );
    }

    $mysqli->close();

    return $data;
}

function GetDataExel(){
	

	$shablon = SimpleXLSX::parse('/var/sqript/products/action.xlsx');
	$stock = SimpleXLSX::parse('/var/www/menufold/data/www/officetorg.com.ua/PI/output_all.xlsx');
	$data['codes'] = [];
	$data['product_stock'] = [];

	 if ($shablon) {
        $sheet = $shablon->rows(0);
        if (is_array($sheet)) {
            foreach ($sheet as $row) {
				if($row[0]){
					$data['codes'][] = array(
						'product_id'=> $row[0],
						'discount' => $row[1]? $row[1]:0 ,
						'super_discont' => $row[2]? $row[2]:0
					);
				}
            }
        } else {
            return null;
        }
    } else {
        return null;
    }
	
	if($stock){
        $sheet = $stock->rows(0);
        if (is_array($sheet)) {
            foreach ($sheet as $row) {
				if($row[0]){
					$data['product_stock'][] = array(
						'product_id'=> $row[1],
						'stock' => $row[2]? $row[2]:0 ,
						'name' => $row[12]
					);
				}
			}

        } else {
            return null;
        }
    } else {
     		
		 return null;
	}
	
	return $data;

}

// Нормализация: приводим product_id и числа к int (на всякий случай)
function normalizeData($data) {
    foreach ($data['codes'] as $k => $v) {
        $data['codes'][$k]['product_id'] = (int)$v['product_id'];
        $data['codes'][$k]['discount'] = (int)$v['discount'];
        $data['codes'][$k]['super_discont'] = (int)$v['super_discont'];
    }

    foreach ($data['product_stock'] as $k => $v) {
        $data['product_stock'][$k]['product_id'] = (int)$v['product_id'];
        $data['product_stock'][$k]['stock'] = (int)$v['stock'];
    }

    return $data;
}

function applyStockLogic($data) {
    $data = normalizeData($data);

    // 1) Индексируем остатки по product_id
    $stockById = array();
    foreach ($data['product_stock'] as $row) {
        $pid = (int)$row['product_id'];
        if ($pid > 0) {
            $stockById[$pid] = $row; // можно хранить и stock, и весь ряд
        }
    }

    // 2) Индексируем акции по product_id (чтобы быстро проверять наличие)
    $codesById = array();
    foreach ($data['codes'] as $row) {
        $pid = (int)$row['product_id'];
        if ($pid > 0) {
            $codesById[$pid] = $row;
        }
    }

    // A) Фильтруем codes: оставляем только те, что есть в остатках
    $filteredCodes = array();
    foreach ($data['codes'] as $row) {
        $pid = (int)$row['product_id'];
        if (isset($stockById[$pid])) {
            $filteredCodes[] = $row;
        }
    }
    $data['codes'] = $filteredCodes;

    // B) Товары в остатках, но без акции
    $data['stock_without_action'] = array();
    foreach ($data['product_stock'] as $row) {
        $pid = (int)$row['product_id'];
        if ($pid > 0 && !isset($codesById[$pid])) {
            $data['stock_without_action'][] = $row;
        }
    }

    return $data;
}

function UpdSpecial($data){
	
	if($data){
		$mysqli_ms = connectbd ('ms');
		$mysqli_ms->query("DELETE FROM `Special`");
		
		foreach ($data as $value){
			$result = $mysqli_ms->query("SELECT * From `Special` WHERE product_id = '".$value['product_id']."'");
			if($result->num_rows){
				$query = "UPDATE `Special` SET discount = ".$value['discount'].",super_discont = ".$value['discount']." WHERE product_id = ".$value['product_id'];
				print_r($mysqli_ms->query($query));
			}else{
				$parametrs = $value;
				$parametrs['id'] = last_id('ms','Special','id');
				query_insert('ms','Special',$parametrs);
			}
		}
		$mysqli_ms->close();
	}
		
}

function correctSpecial ($action_data,$product_stock){
	
	$mysqli_off = connectbd ('off');
	$mysqli_papir = connectbd ('Papir');
	$merchant = array();
	
	$date_start = date('Y-m-d H:i:s',strtotime("now"));
	$date_end = date('Y-m-d',strtotime("+1 days")).' 00:00:00';
	
	$stockById = array();
	foreach ($product_stock as $row) {
		$pid = (int)$row['product_id'];
		if ($pid > 0) {
			$stockById[$pid] = $row;
		}
	}
	
	foreach($action_data as $value){
		
				$query_pr = " SELECT 
	               price,
				   price_cost
				  FROM 
				   product_papir 
				  WHERE  id_off = '".$value['product_id']."'";
				  	  
				  
		$result_pr = $mysqli_papir->query($query_pr);
		while($row_pr = $result_pr->fetch_assoc()){
					
			if($value['super_discont'] && $value['super_discont']>0 ){
					$price_act = $row_pr['price_cost'] - $row_pr['price_cost'] * $value['super_discont']/100;
				}else{
					$price_act = $row_pr['price']  - ($row_pr['price'] - $row_pr['price_cost']) * $value['discount']/100;
				}
				$data [] = array(
					'product_id' => $value['product_id'],
					'price_act' =>   $price_act,
				
				);				
		}
	
	}
      
	foreach ($data as $value) {

		$pid = (int)$value['product_id'];
		$qty = 0;

		if (isset($stockById[$pid])) {
			$qty = (int)$stockById[$pid]['stock'];
		}

		$availability = ($qty > 0) ? 'in stock' : 'out of stock';

		$customerGroups = array(1, 4);

		// 1. Удаляем старые акции по этому товару для нужных customer_group
		foreach ($customerGroups as $customer_group_id) {
			delete_row('off', 'oc_product_special', array(
				array('colum' => 'product_id', 'value' => $pid),
				array('colum' => 'customer_group_id', 'value' => (int)$customer_group_id),
			));
		}

		// 2. Вставляем новые акции
		foreach ($customerGroups as $customer_group_id) {

			$parametrs = array(
				'product_id' => $pid,
				'customer_group_id' => (int)$customer_group_id,
				'price' => $value['price_act'],
				'date_start' => $date_start,
				'date_end' => $date_end
			);

			query_insert('off', 'oc_product_special', $parametrs);
		}

		// 3. В merchant добавляем только одну запись на товар
		$merchant[$pid] = array(
			'productId' => BASE_MERCHANT_ID . $pid,
			'sale_price' => $value['price_act'],
			'sale_price_effective_date' => $date_start . 'T00:00:00Z/' . $date_end . 'T23:59:59Z',
			'availability' => $availability
		);
	}

$merchant = array_values($merchant);

	if ($merchant) {

		logProgress('Preparing Merchant update: ' . count($merchant) . ' items');

		try {
			$client = GetClient();
		} catch (Exception $e) {
			if (strpos($e->getMessage(), 'merchant_auth_required::') === 0) {
				$authUrl = substr($e->getMessage(), strlen('merchant_auth_required::'));

				echo '<div style="padding:16px;background:#fff3cd;border:1px solid #ffe69c;border-radius:8px;margin:16px 0;">';
				echo '<strong>Нужна повторная авторизация Google Merchant.</strong><br><br>';
				echo '<a href="' . htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">Открыть авторизацию Google</a>';
				echo '<br><br>После авторизации вернитесь и снова запустите обновление сайта.';
				echo '</div>';

				return;
			}

			throw $e;
		}

		$service = new ShoppingContent($client);
		$updateEntries = createBatchRequestEntries($merchant, MERCHANT_ID, 'update');

		logProgress('Sending Merchant batch requests...');

		$batchResponse = sendBatchRequests($service, $updateEntries);

		logMerchantSummary($batchResponse);
	}
	
	$mysqli_off->close();
	$mysqli_papir->close();
	
	return;
	
}

function updateProductQuantityFromMS() {
	
		/** 1) Забираем totals из MS **/
	$mysqli_ms = connectbd('ms');

	$sql = "SELECT
				CAST(s.model AS UNSIGNED) AS product_id,
				(s.stock + COALESCE(v.stock, 0)) AS total_qty
			FROM ms.stock_ s
			LEFT JOIN ms.virtual v
				ON v.product_id = CAST(s.model AS UNSIGNED)
			WHERE CAST(s.model AS UNSIGNED) > 0;";

	$res = $mysqli_ms->query($sql);

	$map = array(); // product_id => total_qty
	while ($row = $res->fetch_assoc()) {
		$pid = (int)$row['product_id'];
		if ($pid > 0) {
			$map[$pid] = (int)$row['total_qty'];
		}
	}

	$mysqli_ms->close();

	logInfo('MS rows: ' . count($map));

	if (empty($map)) {
		logError('Nothing to update.');
		exit;
	}

	/** 2) Обновляем quantity в MF (menufold_offtorg) **/
	$mysqli_mf = connectbd('off'); // <-- проверь, что 'off' это menufold_offtorg

	$chunkSize = 600;
	$productIds = array_keys($map);
	$total = count($productIds);

	$batchOk = 0;
	$batchErr = 0;

	for ($i = 0; $i < $total; $i += $chunkSize) {

		$chunk = array_slice($productIds, $i, $chunkSize);

		$case = "CASE product_id ";
		$in = array();

		foreach ($chunk as $pid) {
			$pid = (int)$pid;
			$qty = (int)$map[$pid];

			$case .= "WHEN {$pid} THEN {$qty} ";
			$in[] = $pid;
		}

		$case .= "END";
		$inList = implode(',', $in);

		$upd = "UPDATE oc_product
				SET quantity = {$case}
				WHERE product_id IN ({$inList})";

		if (!$mysqli_mf->query($upd)) {
			$batchErr++;
			logError('MF batch error: ' . $mysqli_mf->error);
		} else {
			$batchOk++;
		}
	}

	$mysqli_mf->close();

	logSuccess('Updated products: ' . $total);
	logSuccess('Batches OK: ' . $batchOk . ', Batches ERR: ' . $batchErr);
	
	
	
	
}


function logMerchantSummary($batchResponse) {

    if (empty($batchResponse)) {
        logError('Merchant response is empty.');
        return;
    }

    $entries = array();

    // если ответ уже массив
    if (is_array($batchResponse)) {
        if (isset($batchResponse['entries']) && is_array($batchResponse['entries'])) {
            $entries = $batchResponse['entries'];
        } else {
            $entries = $batchResponse;
        }
    }

    $total = count($entries);
    $errors = array();
    $successCount = 0;

    foreach ($entries as $entry) {

        $hasError = false;

        if (is_array($entry) && !empty($entry['errors'])) {
            $hasError = true;
            $errors[] = $entry;
        } elseif (is_object($entry) && isset($entry->errors) && !empty($entry->errors)) {
            $hasError = true;
            $errors[] = $entry;
        }

        if (!$hasError) {
            $successCount++;
        }
    }

    logSuccess('Merchant entries processed: ' . $total);
    logSuccess('Merchant success entries: ' . $successCount);

    if (!empty($errors)) {
        logError('Merchant entries with errors: ' . count($errors));

        $shown = 0;
        foreach ($errors as $entry) {
            if ($shown >= 5) {
                break;
            }

            if (is_array($entry)) {
                $entryId = isset($entry['batchId']) ? $entry['batchId'] : 'n/a';
                $msg = 'Merchant error in batchId=' . $entryId;

                if (isset($entry['errors']) && is_array($entry['errors'])) {
                    $parts = array();
                    foreach ($entry['errors'] as $err) {
                        if (is_array($err) && isset($err['message'])) {
                            $parts[] = $err['message'];
                        }
                    }
                    if (!empty($parts)) {
                        $msg .= ': ' . implode(' | ', $parts);
                    }
                }

                logError($msg);
            } else {
                logError('Merchant error entry detected.');
            }

            $shown++;
        }

        if (count($errors) > 5) {
            logInfo('Additional merchant errors hidden: ' . (count($errors) - 5));
        }
    } else {
        logSuccess('Merchant update completed without errors.');
    }
}





?>