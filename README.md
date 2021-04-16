# OpenCart 3 Automater

 The integration of Automater with the OpenCart platform allows you to automatically handle sales and send codes or files to Clients. The cost of handling each transaction is 1% of its value, but not less than 5 cents. Credits for store transactions are not charged - only commission is charged.

## 1. How to install

### 1. Step one, upload

- Download file `automater.ocmod.zip` from repository.
- Go to the admin panel and tab `Extensions -> Installer`.
- Select the previously downloaded package and upload it.

### 2. Step two, modify

- Go to the admin panel and tab `Extensions -> Modifications`.
- Click the blue `refresh` button in the upper right corner under your username.

### 3. Step three, install

- Go to the admin panel and tab `Extensions -> Extensions`.
- Select "Order Totals" from the drop-down list.
- Find "Automater" in the list and click the green install button.

### 4. Step four, configuration

- Go to the admin panel and tab `Extensions -> Extensions`.
- Select "Order Totals" from the drop-down list.
- Find "Automater" in the list and click the blue pencil edit button.

## Important! Problems
In OpenCart, there are often problems that are solved by users and which are not patched in the core.


### * mysqli problems
```bash
Uncaught Exception: Error: Invalid default value for 'date_available'<br />Error No: 1067<br />ALTER TABLE `oc_product` ADD `automater_product_id` VARCHAR(255) NULL in /home/ririen/Pobrane/opencart-3.0.3.7/upload/system/library/db/mysqli.php:41

```

#### workaround:
comment/delete line (15 aprox):
```php
$this->connection->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
```
from file: `/upload/system/library/db/mysqli.php`

Source: https://forum.opencart.com/viewtopic.php?f=206&t=222399#p813328