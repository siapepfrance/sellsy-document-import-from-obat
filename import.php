<?php

    use Teknoo\Sellsy\Sellsy;
    use Teknoo\Sellsy\Transport\Guzzle;
    use Aspera\Spreadsheet\XLSX\Reader;

    require 'vendor/autoload.php';

    $dotenv = new Symfony\Component\Dotenv\Dotenv();
    $dotenv->load(__DIR__.'/.env');

	// Web scraping.

    function getClient(){
        $transportBridge = new Guzzle(new \GuzzleHttp\Client());
        $sellsy = new Sellsy(
            'https://apifeed.sellsy.com/0/',
            $_ENV['userToken'],
            $_ENV['userSecret'],
            $_ENV['customerToken'],
            $_ENV['customerSecret']
        );
        return $sellsy->setTransport($transportBridge);
    }

    function getSellsyClients() {
        $criteria = ['order' => ['order' => 'joindate', 'direction' => 'DESC'], 'pagination' => ['nbperpage' => 5000, 'pagenum' => 1]];
        $data = getClient()->Client()->getList($criteria)->getResponse();
        $clients = [];
        foreach ($data['result'] as $key => $row) {
            $clients[] = $row;
        }
        return $clients;
    }

    function getSellsyQuotations() {
        $criteria = ['doctype' => 'estimate', 'order' => ['order' => 'DESC'], 'pagination' => ['nbperpage' => 5000, 'pagenum' => 1]];
        $data = getClient()->Document()->getList($criteria)->getResponse();
        $clients = [];
        foreach ($data['result'] as $key => $row) {
            $clients[] = $row;
        }
        return $clients;
    }

    function getSellsyInvoices() {
        $criteria = ['doctype' => 'invoice', 'order' => ['order' => 'DESC'], 'pagination' => ['nbperpage' => 5000, 'pagenum' => 1]];
        $data = getClient()->Document()->getList($criteria)->getResponse();
        $clients = [];
        foreach ($data['result'] as $key => $row) {
            $clients[] = $row;
        }
        return $clients;
    }

    function getSellsyCreditNotes() {
        $criteria = ['doctype' => 'creditnote', 'order' => ['order' => 'DESC'], 'pagination' => ['nbperpage' => 5000, 'pagenum' => 1]];
        $data = getClient()->Document()->getList($criteria)->getResponse();
        $clients = [];
        foreach ($data['result'] as $key => $row) {
            $clients[] = $row;
        }
        return $clients;
    }

    function createDocument($documentType,
                             $parentId,
                             $clientId,
                             $ident,
                             $displayedDate,
                             $expireDate,
                             $subject,
                             $notes,
                             $currency,
                             $doclayoutid,
                             $docspeakerStaffId,
                             $corpAddressId,
                             $thirdAddressId,
                             $shipAddressId,
                             $rows
    ) {
        try {
            $client = getClient();
            $documentNode = array(
                'doctype'           => $documentType,/* Document type : invoice, estimate or creditnote */
                'thirdid'           => $clientId,/* Client Number on Sellsy */
                'ident'             => $ident,/* Document Number : example F-202111-001 */
                'displayedDate'     => $displayedDate->getTimestamp(),
                'expireDate'        => $expireDate->getTimestamp(),
                'subject'           => $subject, /* Champs description Obat */
                'notes'             => $notes, /* Champs notes bas de page Obat */
                //'displayShipAddress'=> '{{displayshippaddress_enum}}',
                //'rateCategory'      => '{{rateCategory}}',
                //'globalDiscount'    => '{{globalDiscount}}',
                //'globalDiscountUnit'=> '{{globalDiscountUnit}}',
                //'hasTvaLawText'     => '{{hasTvaLawText}}',
                'currency'          => $currency,
                'doclayout'         => $doclayoutid,
                'doclang'           => '0',
                'docspeakerStaffId'	=> $docspeakerStaffId,/* Document's creator */
                'showContactOnPdf'	=> 'N',
                'showParentOnPdf'	=> 'N',
                'conditionDocShow'  => 'N'
            );

            if ($parentId) {
                $documentNode = array_merge($documentNode, array(
                    'parentId'          => $parentId,/* Parent Id : for example if you want to associate an invoice to an estimate you can provide the estimate docid here */
                ));
            }

            $request = array(
                'document' => $documentNode,
                'thirdaddress' => array(
                    'id' => $thirdAddressId
                ),
                'shipaddress' => array(
                    'id' => $shipAddressId
                ),
                'row' => array_map(function($row) {
                    $hasUnitAmount = str_replace(' ', '', $row['unitAmount']) != '';
                    $hasUnit = str_replace(' ', '', $row['unit']) != '';
                    $hasQuantity = str_replace(' ', '', $row['qt']) != '';
                    $isDecriptiveRow = !($hasUnitAmount && $hasUnit && $hasQuantity);
                    return array (
                        'row_type'      => $isDecriptiveRow ? 'comment' : 'once',
                        'row_name'      => $row['reference'],
                        'row_notes'      => $row['name'],
                        'row_unit'      => $isDecriptiveRow ? '' : $row['unit'],
                        'row_unitAmount' => $isDecriptiveRow ? '' : floatval($row['unitAmount']),
                        'row_taxid'     => $isDecriptiveRow ? '' : $row['taxid'],
                        'row_qt'        => $isDecriptiveRow ? '' : intval($row['qt'])
                    );
                }, $rows)
            );
            $response = $client->Document()->create($request)->getResponse();
            echo sprintf('1 %s imported successfully<br/>', $documentType);
            return $response;
        } catch(\Teknoo\Sellsy\Client\Exception\ErrorException $exception) {
            $errorText = '<br/><br/>' . sprintf('%s %s', $exception->getCode(), $exception->getMessage()) . '<br/><br/>';
            echo sprintf('A Sellsy error has occured when trying to create a document with the following request :<br/><br/> %s <br/><br/> error message : %s <br/><br/>', json_encode($request), $errorText);
            echo 'So the process has been stopped for more safety <br/>';
            exit;
        } catch(\Exception $exception) {
            $errorText = '<br/><br/>' . sprintf('%s %s', $exception->getCode(), $exception->getMessage()) . '<br/><br/>';
            echo sprintf('An error has occured when trying to create a document with the following request :<br/><br/> %s <br/><br/> error message : %s <br/><br/>', json_encode($request), $errorText);
            echo 'So the process has been stopped for more safety <br/>';
            exit;
        }
    }

    function downloadDocument($id, $format = 'xlsx') {
        $client = getClient(array_merge(['Content-Type' => 'application/json']));
        $path = sprintf('/app/documents/export/%s?download=1&exportType=%s', $id, $format);
        return $client->request('POST', $path, [])->getBody();
    }

    function importDocuments($clientList) {
        $quotationList = getSellsyQuotations();
        $quotationsToCreate = getDocumentsToCreate('Devis', 'estimate', $clientList, $quotationList, function($ident, $row){ return $ident === $row['ident']; }, [], function($parentIdent, $row){ return $parentIdent === $row['ident']; });
        echo sprintf('%d quotations to import<br/>', count($quotationsToCreate));

        // Quotations
        foreach ($quotationsToCreate as $key => $row) {
            executeQuotationImport($row['fileName'], $row['clientId'], $row['ident'], $row['displayedDate'], $row['expireDate'], $row['subject'], $row['notes'], $row['thirdAddressId'], $row['thirdAddressId'], $row['rows'], $row['alreadyExist']);
        }

        $invoiceList = getSellsyInvoices();
        $quotationList = getSellsyQuotations();
        $invoicesToCreate = getDocumentsToCreate('Facture', 'invoice', $clientList, $invoiceList, function($ident, $row){ return $ident === $row['ident']; }, $quotationList, function($parentIdent, $row){ return $parentIdent === $row['ident']; });
        echo sprintf('%d invoices à import<br/>', count($invoicesToCreate));

        // Invoices
        foreach ($invoicesToCreate as $key => $row) {
            executeInvoiceImport($row['fileName'], $row['clientId'], $row['parentId'], $row['ident'], $row['displayedDate'], $row['expireDate'], $row['subject'], $row['notes'], $row['thirdAddressId'], $row['thirdAddressId'], $row['rows'], $row['alreadyExist']);
        }

        $creditNoteList = getSellsyCreditNotes();
        $invoiceList = getSellsyInvoices();
        $creditnotesToCreate = getDocumentsToCreate('Avoir', 'creditnote', $clientList, $creditNoteList, function($ident, $row){ return $ident === $row['ident']; }, $invoiceList, function($parentIdent, $row){ return $parentIdent === $row['ident']; });
        echo sprintf('%d credit notes to import<br/>', count($creditnotesToCreate));

        // Credit notes
        foreach ($creditnotesToCreate as $key => $row) {
            executeCreditNoteImport($row['fileName'], $row['clientId'], $row['parentId'], $row['ident'], $row['displayedDate'], $row['expireDate'], $row['subject'], $row['notes'], $row['thirdAddressId'], $row['thirdAddressId'], $row['rows'], $row['alreadyExist']);
        }
    }

    function getDocumentsToCreate($searchCriteria, $documentType, $clientList, $existingDocumentList, $existFunction, $parentDocumentList, $parentExistFunction) {
        //Get a list of file paths using the glob function.
        $fileList = glob('not-treated/*');
        $filteredFiles = array_filter($fileList, function($fileName) use($searchCriteria) {
            return stripos($fileName, $searchCriteria) != false;
        });

        //Loop through the array that glob returned.
        $key = 0;
        $reader = new Reader();
        $documents = [];

        foreach($filteredFiles as $filename){
                // Technical Pointer to read the document
                $isHeaderFound = false;
                $isInRows = false;
                $isInMainInformations = false;

                // Extracted information
                $clientObject = null;
                $clientId = null;
                $ident = null;
                $parendId = null;
                $parentIdent = null;
                $displayedDate = null;
                $expireDate = null;
                $subject = null;
                $chantierTemp = null;
                $notes = null;
                $thirdAddressId = null;
                $documentRows = [];
                $columnIds = [
                    'number' => null,
                    'reference' => null,
                    'designation' => null,
                    'quantity' => null,
                    'unit' => null,
                    'unitPrice' => null,
                    'vat' => null,
                    'totalTaxFree' => null
                ];

                // Execution
                $reader->open($filename);
                foreach ($reader as $row) {
                    if( hasValueInArray($row, 0,'', true) &&
                        hasValueInArray($row, 1,'', true)) {
                        $isInRows = false;
                        $isInMainInformations = true;
                    }

                    if($isInRows === true && isset($row[$columnIds['unit']])) {
                        $trimmedUnitAmount = str_replace(' ', '', $row[$columnIds['unitPrice']]);
                        $unitAmountEqualZero = $trimmedUnitAmount == '0,00€';
                        $documentRows[] = [
                            'reference'  => $columnIds['reference'] !== false ? (str_replace(' ', '', $row[$columnIds['reference']]) != '' ? ($row[$columnIds['reference']]) : (round($row[$columnIds['number']], 2))) : (round($row[$columnIds['number']], 2)),
                            'name'       => $row[$columnIds['designation']],
                            'unit'       => 'u'/*$row[$columnIds['unit']]*/,
                            'unitAmount' => $unitAmountEqualZero ? '' : str_replace(['€', ',', ' '], ['', '.', ''], $row[$columnIds['unitPrice']]),
                            'taxid'      => getTaxidFromVat($row[$columnIds['vat']]),
                            'qt'         => $row[$columnIds['quantity']]
                        ];
                    }

                    if($isInMainInformations) {
                        if(isset($row[0]) && str_replace(' ', '', $row[0]) != '') {
                            $startColumn = 0;
                        } elseif(isset($row[1]) && str_replace(' ', '', $row[1]) != '') {
                            $startColumn = 1;
                        } elseif(isset($row[2]) && str_replace(' ', '', $row[2]) != '') {
                            $startColumn = 2;
                        } else {
                            $startColumn = 1;
                        }

                        if($ident == null && isset($row[$startColumn]) && stripos($row[$startColumn], $searchCriteria) !== false) {
                            $splited = explode(' : ', $row[$startColumn]);
                            $ident = isset($splited[1]) ? str_replace('/', '-', trim($splited[1])) : null;
                        } elseif(isset($row[$startColumn]) && stripos($row[$startColumn], 'En date du') !== false) {
                            $splited = explode(':', $row[$startColumn]);
                            $displayedDate = isset($splited[1]) ? \DateTime::createFromFormat('d/m/Y', trim($splited[1]))->setTime(0,0,0) : null;
                        } elseif(isset($row[$startColumn]) && (stripos($row[$startColumn], 'Valable jusqu\'au') !== false || stripos($row[$startColumn], 'Échéance') !== false )) {
                            $splited = explode(' : ', $row[$startColumn]);
                            $expireDate = isset($splited[1]) ? \DateTime::createFromFormat('d/m/Y', trim($splited[1]))->setTime(0,0,0) : null;
                        } elseif(isset($row[$startColumn]) && stripos($row[$startColumn], 'Note bas de page') !== false) {
                            $splited = explode(' : ', $row[$startColumn]);
                            $notes = isset($splited[1]) ? trim($splited[1]) : null;
                        } elseif(isset($row[$startColumn]) && stripos($row[$startColumn], 'Chantier') !== false) {
                            $splited = explode(' : ', $row[$startColumn]);
                            $chantierTemp = isset($splited[1]) ? trim($splited[1]) : null;
                        } elseif(isset($row[$startColumn]) && stripos($row[$startColumn], 'Description') !== false) {
                            $splited = explode(' : ', $row[$startColumn]);
                            $subject = isset($splited[1]) && trim($splited[1]) != null ? (trim($splited[1]) . ' - ' . $chantierTemp) : $chantierTemp;
                        } elseif(isset($row[$startColumn]) && stripos($row[$startColumn], 'id_client_sellsy') !== false) {
                            $splited = explode(':', $row[$startColumn]);
                            $clientId = isset($splited[1]) ? trim($splited[1]) : null;
                            $clientSeek = array_filter($clientList, function($row) use($clientId) { return $row['thirdid'] == $clientId; });
                            $clientObject = count($clientSeek) == 1 ? $clientSeek[array_keys($clientSeek)[0]] : null;
                            $thirdAddressId = $clientObject && isset($clientObject['mainaddressid']) ? $clientObject['mainaddressid'] : null;
                        } elseif(isset($row[$startColumn]) && (stripos($row[$startColumn], 'numero_devis_obat') !== false || stripos($row[$startColumn], 'numero_facture_obat') !== false)) {
                            $splited = explode(':', $row[$startColumn]);
                            $parentIdent = isset($splited[1]) ? str_replace('/', '-', trim($splited[1])) : null;
                        } else {

                        }
                    }

                    if( hasValueInArray($row, 0,'Numéro') &&
                        hasValueInArray($row, 1,'Désignation') ||
                        hasValueInArray($row, 0,'Numéro') &&
                        hasValueInArray($row, 1,'Référence')) {
                        $isInRows = true;
                        $columnIds = [
                            'number' => getValueIndexInRows($row, 'Numéro'),
                            'reference' => getValueIndexInRows($row, 'Référence'),
                            'designation' => getValueIndexInRows($row, 'Désignation'),
                            'quantity' => getValueIndexInRows($row, 'Quantité'),
                            'unit' => getValueIndexInRows($row, 'Unité'),
                            'unitPrice' => getValueIndexInRows($row, 'Prix unitaire HT'),
                            'vat' => getValueIndexInRows($row, 'TVA'),
                            'totalTaxFree' => getValueIndexInRows($row, 'Total HT')
                        ];
                    }
                }

                $reader->close();
                $alreadyExist = count(array_filter($existingDocumentList, function ($row) use($existFunction, $ident) { return $existFunction($ident, $row); })) === 1;
                $parentDocumentList = array_filter($parentDocumentList, function ($row) use($parentExistFunction, $parentIdent) { return $parentExistFunction($parentIdent, $row); });
                $parentDocument = count($parentDocumentList) === 1 ? $parentDocumentList[0] : null;
                $parentExist = $parentDocument != null;
                if ($parentExist && isset($parentDocument['docid'])) {
                    $parendId = $parentDocument['docid'];
                } else {
                    echo sprintf('Parent document does not exist for following %s : %s<br/><br/>', $documentType, json_encode($row));
                    exit;
                }

                $document = [
                    'fileName' => $filename,
                    'documentType' => $documentType,
                    'clientId' => $clientId,
                    'parentId' => $parendId,
                    'thirdAddressId' => $thirdAddressId,
                    'ident' => $ident,
                    'displayedDate' => $displayedDate,
                    'expireDate' => $expireDate,
                    'subject' => $subject,
                    'notes' => $notes,
                    'rows' => $documentRows,
                    'alreadyExist' => $alreadyExist
                ];

                if (is_array($parentDocumentList) && count($parentDocumentList) > 0) {
                    $document = array_merge($document, ['parentExist' => $parentExist]);
                    if ($parendId == null) {
                        echo sprintf('Parent document does not exist for following document :<br/><br/> %s <br/><br/>', json_encode($document));
                        echo 'So the process has been stopped for more safety <br/>';
                        exit;
                    }
                } else {
                    $document = array_merge($document, ['parentExist' => null]);
                }

                $documents[] = $document;

            $key++;
        }

        return $documents;
    }

    function hasValueInArray($array, $index, $value, $trim = false) {
        if($trim) {
            return isset($array[$index]) && str_replace(' ', '', $array[$index]) === $value;
        }
        return isset($array[$index]) && $array[$index] === $value;
    }

    function getValueIndexInRows($rows, $value) {
        foreach ($rows as $key => $row) {
            if($row === $value) {
                return $key;
            }
        }
        return false;
    }

    function getTaxidFromVat($vat) {
        $trimedVat = str_replace(' ', '', trim($vat));
        if(stripos($trimedVat, '2,10%') !== false || stripos($trimedVat, '2,1%') !== false){
            return '3992021';
        }
        if(stripos($trimedVat, '5,50%') !== false || stripos($trimedVat, '5,5%') !== false){
            return '3992020';
        }
        if(stripos($trimedVat, '8,50%') !== false || stripos($trimedVat, '8,5%') !== false){
            return '3992019';
        }
        if(stripos($trimedVat, '10%') !== false || stripos($trimedVat, '10,00%') !== false){
            return '3992018';
        }
        if(stripos($trimedVat, '20%') !== false || stripos($trimedVat, '20,00%') !== false){
            return '3992017';
        }
        if(stripos($trimedVat, '0%') !== false || stripos($trimedVat, '0,00%') !== false){
            return '3992022';
        }
        return '3992022';
    }

    function executeQuotationImport($fileName, $clientId, $ident, $displayedDate, $expireDate, $subject, $notes, $thirdAddressId, $shipAddressId, $rows, $alreadyExist) {
        if ($alreadyExist) {
            echo 'Quotation already exist<br/><br/>';
        } else {
            $result = createDocument('estimate', null, $clientId, $ident, $displayedDate, $expireDate, $subject, $notes, '1', '108970', '193044', '121644684', $thirdAddressId, $shipAddressId, $rows);
            if ($result !== false) {
                rename($fileName, str_replace('not-treated/', 'treated/', $fileName));
            }
        }
    }

    function executeInvoiceImport($fileName, $clientId, $parentId, $ident, $displayedDate, $expireDate, $subject, $notes, $thirdAddressId, $shipAddressId, $rows, $alreadyExist) {
        if ($alreadyExist) {
            echo 'Invoice already exist<br/><br/';
        } else {
            $result = createDocument('invoice', $parentId, $clientId, $ident, $displayedDate, $expireDate, $subject, $notes, '1', '104853', '193044', '121644684', $thirdAddressId, $shipAddressId, $rows);
            if ($result !== false) {
                rename($fileName, str_replace('not-treated/', 'treated/', $fileName));
            }
        }
    }

    function executeCreditNoteImport($fileName, $clientId, $parentId, $ident, $displayedDate, $expireDate, $subject, $notes, $thirdAddressId, $shipAddressId, $rows, $alreadyExist) {
        if ($alreadyExist) {
            echo 'Credit note already exist<br/><br/';
        } else {
            $result = createDocument('creditnote', $parentId, $clientId, $ident, $displayedDate, $expireDate, $subject, $notes, '1', null, '193044', '121644684', $thirdAddressId, $shipAddressId, $rows);
            if ($result !== false) {
                rename($fileName, str_replace('not-treated/', 'treated/', $fileName));
            }
        }
    }

    $clientList = getSellsyClients();


    importDocuments($clientList);

    echo '<br/>All imports are done !';

?>