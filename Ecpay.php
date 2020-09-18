<?php
require_once dirname(__FILE__).'/ECPay.Payment.Integration.php';

/**
 *
 */
class Ecpay
{
    /**
     * 帶入ECPay提供的HashKey
     */
    private $HashKey = '';

    /**
     * 帶入ECPay提供的MerchantID
     */
    private $MerchantID = '';

    /**
     * 帶入ECPay提供的HashIV
     */
    private $HashIV = '';

    /**
     * CheckMacValue加密類型，請固定填入1，使用SHA256加密
     */
    private $EncryptType = 1;

    /**
     * 服務位置
     */
    private $ServiceURL = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5';

    /**
     * 款完成通知轉向位置
     */
    private $ClientBackURL = '';

    /**
     * 付款完成通知回傳的網址
     */
    private $ReturnURL = '';

    /**
     * 使用電子發票
     */
    private $useInvoice = false;

    /**
     * 訂單資訊
     */
    private $orderInfo = [];

    /**
     * 信用卡額外設定
     */
    private $creditConfig = [];

    /**
     * atm額外設定
     */
    private $atmConfig = [];

    /**
     * 超商額外設定
     */
    private $barcodeConfig = [];

    /**
     * 超商額外設定
     */
    private $cvsConfig = [];

    /**
     * Ecpay Construct
     */
    function __construct($config = [])
    {
        foreach ($config as $key => $value) {
            if (isset($this->$key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * 付款
     */
    public function pay($orderInfo, $method = 'atm')
    {
        $this->orderInfo = $orderInfo;

        $obj = new ECPay_AllInOne();

        $obj->ServiceURL = $this->ServiceURL;
        $obj->HashKey = $this->HashKey ;
        $obj->HashIV = $this->HashIV ;
        $obj->MerchantID = $this->MerchantID;
        $obj->EncryptType = $this->EncryptType;

        $obj->Send['ReturnURL'] = $this->ReturnURL;
        $obj->Send['MerchantTradeNo'] = $orderInfo['number'];
        $obj->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
        $obj->Send['TotalAmount'] = $orderInfo['total'];
        $obj->Send['TradeDesc'] = "綠界付款";
        $obj->Send['ChoosePayment'] = $this->selectPayMethod($method);
        $obj->Send['ClientBackURL'] = $this->ClientBackURL;

        $obj = $this->additionalSend($obj, $method);

        foreach ($orderInfo['good'] as $good) {
            array_push(
                $obj->Send['Items'], [
                    'Name' => $good['name'],
                    'Price' => $good['price'],
                    'Currency' => "元",
                    'Quantity' => $good['qty'],
                    'URL' => ''
                ]
            );
        }

        if ($this->useInvoice === true) {
            $obj = $this->getInvoice($obj);
        }

        $obj->CheckOut();
    }

    /**
     * 付款方式
     */
    private function selectPayMethod($method)
    {
        switch ($method) {
            case 'credit':
                return ECPay_PaymentMethod::Credit;
                break;

            case 'atm':
                return ECPay_PaymentMethod::ATM;
                break;

            case 'barcode':
                return ECPay_PaymentMethod::BARCODE;
                break;

            case 'cvs':
                return ECPay_PaymentMethod::CVS;
                break;

            case 'webatm':
                return ECPay_PaymentMethod::WebATM;
                break;

            case 'googlepay':
                return ECPay_PaymentMethod::GooglePay;
                break;

            default:
                return ECPay_PaymentMethod::ALL;
                break;
        }
    }

    /**
     * 額外參數
     */
    private function additionalSend($obj, $method)
    {
        switch ($method) {
            case 'credit':
                if (!empty($this->creditConfig)) {
                    switch ($this->creditConfig['select']) {
                        case 'installment':
                            $obj->SendExtend['CreditInstallment'] = $this->creditConfig['CreditInstallment'] ; //分期期數，預設0(不分期)，信用卡分期可用參數為:3,6,12,18,24
                            $obj->SendExtend['Redeem'] = $this->creditConfig['Redeem']; //是否使用紅利折抵，預設false
                            $obj->SendExtend['UnionPay'] = $this->creditConfig['UnionPay'];  //是否為聯營卡，預設false;
                            break;

                        default:
                            //Credit信用卡定期定額付款延伸參數(可依系統需求選擇是否代入)
                            //以下參數不可以跟信用卡分期付款參數一起設定
                            $obj->SendExtend['PeriodAmount'] = $this->creditConfig['PeriodAmount']; //每次授權金額，預設空字串
                            $obj->SendExtend['PeriodType'] = $this->creditConfig['PeriodType']; //週期種類，預設空字串
                            $obj->SendExtend['Frequency'] = $this->creditConfig['Frequency']; //執行頻率，預設空字串
                            $obj->SendExtend['ExecTimes'] = $this->creditConfig['ExecTimes']; //執行次數，預設空字串
                            break;
                    }
                }
                break;

            case 'atm':
                if (!empty($this->atmConfig)) {
                    $obj->SendExtend['ExpireDate'] = $this->atmConfig['ExpireDate']; //繳費期限 (預設3天，最長60天，最短1天)
                    $obj->SendExtend['PaymentInfoURL'] = $this->atmConfig['PaymentInfoURL']; //伺服器端回傳付款相關資訊。
                }
                break;

            case 'barcode':
                if (!empty($this->barcodeConfig)) {
                    $obj->SendExtend['Desc_1'] = $this->barcodeConfig['Desc_1']; //交易描述1 會顯示在超商繳費平台的螢幕上。預設空值
                    $obj->SendExtend['Desc_2'] = $this->barcodeConfig['Desc_2']; //交易描述2 會顯示在超商繳費平台的螢幕上。預設空值
                    $obj->SendExtend['Desc_3'] = $this->barcodeConfig['Desc_3']; //交易描述3 會顯示在超商繳費平台的螢幕上。預設空值
                    $obj->SendExtend['Desc_4'] = $this->barcodeConfig['Desc_4']; //交易描述4 會顯示在超商繳費平台的螢幕上。預設空值
                    $obj->SendExtend['PaymentInfoURL'] = $this->barcodeConfig['PaymentInfoURL']; //預設空值
                    $obj->SendExtend['ClientRedirectURL'] = $this->barcodeConfig['ClientRedirectURL']; //預設空值
                    $obj->SendExtend['StoreExpireDate'] = $this->barcodeConfig['StoreExpireDate']; //預設空值
                }
                break;

            case 'cvs':
                if (!empty($this->cvsConfig)) {
                    $obj->SendExtend['Desc_1'] = $this->cvsConfig['Desc_1']; //交易描述1 會顯示在超商繳費平台的螢幕上。預設空值
                    $obj->SendExtend['Desc_2'] = $this->cvsConfig['Desc_2']; //交易描述2 會顯示在超商繳費平台的螢幕上。預設空值
                    $obj->SendExtend['Desc_3'] = $this->cvsConfig['Desc_3']; //交易描述3 會顯示在超商繳費平台的螢幕上。預設空值
                    $obj->SendExtend['Desc_4'] = $this->cvsConfig['Desc_4']; //交易描述4 會顯示在超商繳費平台的螢幕上。預設空值
                    $obj->SendExtend['PaymentInfoURL'] = $this->cvsConfig['PaymentInfoURL']; //預設空值
                    $obj->SendExtend['ClientRedirectURL'] = $this->cvsConfig['ClientRedirectURL']; //預設空值
                    $obj->SendExtend['StoreExpireDate'] = $this->cvsConfig['StoreExpireDate']; //預設空值
                }
                break;
        }

        return $obj;
    }

    /**
     * 電子發票
     */
    private function getInvoice($obj)
    {
        $obj->Send['InvoiceMark'] = ECPay_InvoiceState::Yes;
        $obj->SendExtend['RelateNumber'] = $this->orderInfo['number'];
        $obj->SendExtend['CustomerEmail'] = $this->orderInfo['email'];
        $obj->SendExtend['CustomerPhone'] = $this->orderInfo['tel'];
        $obj->SendExtend['TaxType'] = ECPay_TaxType::Dutiable;
        $obj->SendExtend['CustomerAddr'] = $this->orderInfo['address'];
        $obj->SendExtend['InvoiceItems'] = [];

        // 將商品加入電子發票商品列表陣列
        foreach ($obj->Send['Items'] as $info)
        {
            array_push($obj->SendExtend['InvoiceItems'],[
                'Name' => $info['Name'],
                'Count' => $info['Quantity'],
                'Word' => '個',
                'Price' => $info['Price'],
                'TaxType' => ECPay_TaxType::Dutiable
            ]);
        }
        $obj->SendExtend['InvoiceRemark'] = '';
        $obj->SendExtend['DelayDay'] = '0';
        $obj->SendExtend['InvType'] = ECPay_InvType::General;

        return $obj;
    }

    /**
     * 回傳檢查
     *
     * 如果成功記得回傳 1|OK，不然綠界系統會寫排程在重複post動作
     */
    public function returnCheck()
    {
        try {
            $obj = new ECPay_AllInOne();
            $obj->MerchantID = $this->MerchantID;
            $obj->HashKey = $this->HashKey;
            $obj->HashIV = $this->HashIV;
            $obj->EncryptType = ECPay_EncryptType::ENC_SHA256; // SHA256
            $feedback = $obj->CheckOutFeedback();
            return [
                'status' => 'success',
                'message' => '1|OK',
                'feedback' => $feedback
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => '0|' . $e->getMessage()
            ]
        }

    }
 }
