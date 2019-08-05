# 基本介紹
- 將多個付款方式結合一起
- 提供All in one / 信用卡 / ATM / 超商 / WebATM
- SDK請至綠界下載PHP版本 : https://github.com/ECPay/ECPayAIO_PHP/tree/master/AioSDK/sdk

# 環境
- php 5

# 操作

<h3>設定記本參數</h3>

```php
$config = [
  'HashKey' => '',
  'HashIV' => '',
  'MerchantID' => '',
  'ReturnURL' => '', //付款完成後綠界會回傳結果的位置
  'ClientBackURL' => '', //付款完成後回到的位置
  'ServiceURL' => '', //如果今天還在測試階段，在填入測試位置，如上線，就不需要此變數
  'creditConfig' => [], //如果要使用分期付款或是定額付款再帶入參數
  'atmConfig' => [], //如果有限制付款天數在填入
  'barcodeConfig' => [], //如有要敘述在操作機器上再填入
  'cvsConfig' => [] //如有要敘述在操作機器上再填入
];
$Ecpay = new Ecpay($config);
```

<h3>使用各種付款方式</h3>

```php
$orderInfo = [
  'number' => 'test123',
  'total' => 10
];

//credit - atm - barcode - cvs - webatm - googlepay
$Ecpay->pay($orderInfo, 'atm');
```

# 注意事項

<h3>付款方式回傳接收</h3>

```php
$Ecpay->returnCheck();
```

**記得要印出 1|OK ， 不然綠界那邊會設定排程，幾天內會繼續朝同一個回傳位置進行post動作
