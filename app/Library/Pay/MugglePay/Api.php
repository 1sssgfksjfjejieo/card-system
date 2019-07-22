<?php
namespace App\Library\Pay\MugglePay; use App\Library\CurlRequest; use App\Library\Pay\ApiInterface; use Illuminate\Support\Facades\Log; class Api implements ApiInterface { private $url_notify = ''; private $url_return = ''; public function __construct($sp3c46ab) { $this->url_notify = SYS_URL_API . '/pay/notify/' . $sp3c46ab; $this->url_return = SYS_URL . '/pay/return/' . $sp3c46ab; } function goPay($sp9d4382, $sp2e47fc, $spd4e90d, $spd0789a, $sp076ec7) { if (!isset($sp9d4382['app_secret'])) { throw new \Exception('请填写[app_secret] (后台应用密钥)'); } $this->url_return .= '/' . $sp2e47fc; $spc0e525 = array('merchant_order_id' => $sp2e47fc, 'price_amount' => sprintf('%.2f', $sp076ec7 / 100), 'price_currency' => 'CNY', 'pay_currency' => $sp9d4382['payway'] !== 'COIN' ? $sp9d4382['payway'] : '', 'title' => $spd4e90d, 'description' => $spd0789a, 'callback_url' => $this->url_notify, 'cancel_url' => $this->url_return, 'success_url' => $this->url_return, 'token' => md5($sp9d4382['app_secret'] . $sp2e47fc . config('app.key'))); $sp42422c = CurlRequest::post('https://api.mugglepay.com/v1/orders', json_encode($spc0e525), array('Content-Type' => 'application/json', 'token' => $sp9d4382['app_secret'])); $sp9b52fe = @json_decode($sp42422c, true); if (!$sp9b52fe || !isset($sp9b52fe['status']) || $sp9b52fe['status'] !== 201) { Log::error('Pay.MugglePay.goPay.order, request failed', array('response' => $sp42422c)); throw new \Exception('获取付款信息超时, 请刷新重试'); } \App\Order::whereOrderNo($sp2e47fc)->update(array('pay_trade_no' => $sp9b52fe['order']['order_id'])); header('Location: ' . $sp9b52fe['payment_url']); die; } function verify($sp9d4382, $sp9a4d97) { $sp7b2182 = isset($sp9d4382['isNotify']) && $sp9d4382['isNotify']; if ($sp7b2182) { if (!isset($_POST['merchant_order_id']) || !isset($_POST['token'])) { Log::error('Pay.MugglePay.verify, request invalid', array('$_POST' => $_POST)); echo json_encode(array('status' => 400)); return false; } $sp2e47fc = $_POST['merchant_order_id']; if ($_POST['token'] !== md5($sp9d4382['app_secret'] . $sp2e47fc . config('app.key'))) { Log::error('Pay.MugglePay.verify, token illegal', array('$_POST' => $_POST)); echo json_encode(array('status' => 403)); return false; } if ($_POST['pay_currency'] !== 'CNY') { Log::error('Pay.MugglePay.verify, currency illegal', array('$_POST' => $_POST)); echo json_encode(array('status' => 415)); return false; } if ($_POST['status'] === 'PAID') { $spca4fc7 = $_POST['order_id']; $spa7b5ad = (int) round($_POST['pay_amount'] * 100); $sp9a4d97($sp2e47fc, $spa7b5ad, $spca4fc7); echo json_encode(array('status' => 200)); return true; } else { Log::error('Pay.MugglePay.verify, status illegal', array('$_POST' => $_POST)); } echo json_encode(array('status' => 406)); return false; } else { $sp2e47fc = @$sp9d4382['out_trade_no']; if (strlen($sp2e47fc) < 5) { throw new \Exception('交易单号未传入'); } $spca4fc7 = \App\Order::whereOrderNo($sp2e47fc)->firstOrFail()->pay_trade_no; $sp42422c = CurlRequest::get('https://api.mugglepay.com/v1/orders/' . $spca4fc7, array('token' => $sp9d4382['app_secret'])); $sp9b52fe = @json_decode($sp42422c, true); if (!$sp9b52fe || !isset($sp9b52fe['status'])) { Log::error('Pay.MugglePay.verify, request failed', array('response' => $sp42422c)); return false; } if ($sp9b52fe['order']['pay_currency'] === 'CNY') { if ($sp9b52fe['order']['status'] === 'PAID') { $spca4fc7 = $sp9b52fe['order']['order_id']; $spa7b5ad = (int) round($sp9b52fe['order']['pay_amount'] * 100); $sp9a4d97($sp2e47fc, $spa7b5ad, $spca4fc7); return true; } else { Log::error('Pay.MugglePay.verify, status illegal', array('response' => $sp42422c)); } } else { Log::error('Pay.MugglePay.verify, currency illegal', array('response' => $sp42422c)); } return false; } } }