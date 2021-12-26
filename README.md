Obat Document Export
====================

This tool allow you to import easily your documents from Obat to Sellsy when migrating your CRM.
First, you have to export export your Obat documents (quotations, invoice, credits) as xlsx or csv format using this script => https://github.com/siapepfrance/obat-document-export

Getting Started
---------------
#### 1 - Install dependencies
```
$ php -d memory_limit=-1 composer.phar install
```

#### 2 - Create the .env file
```
$ cp .env.dist .env
```

#### 3 - Fill the .env file with your Obat account configurations
* 1 - Log in to your Sellsy account
* 2 - Open the Menu > Setting > Developer Portal > API
* 3 - Generate a "consumer token" and a "user token"
* 4 - Grab the following values ( userToken, userSecret, customerToken, customerSecret ) and fill the .env file with

#### 4 - Put your Obat exported files in the folder "not-treated"

#### 5 - Launch the web server
```bash
php -S 127.0.0.1:8001 import.php
```

#### 6 - Import from your browser
Open the URL http://127.0.0.1:8001?documentType=TYPE_OF_DOCUMENT in your browser and wait until you see it is written "All imports are done"
Available TYPE_OF_DOCUMENT are : quotation (devis), invoice (facture), credit (avoir). If you dont provide it, it will display you an error.

PS : The right import order is (1) - quotation, (2) - invoice (3) - credit. But it is up to you to manage your own specific case

