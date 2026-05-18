<?php
// checkout.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. 引入你刚刚手动上传的 Stripe 核心库
require_once 'stripe-php/init.php';

// 2. 填入你的 Stripe Secret Key
\Stripe\Stripe::setApiKey('sk_test_51TUQVj2KsAgaxEpqpUfrGrr3Ut9JmRLzJsIlpP4BXNYYgfT6W4pU0o8a1nERBh3h7Wqug7PUN9qe3P5jjQ0FYGJJ00G6jf5Cu8');

// 3. 获取前端 (event_details.php) 传过来的 event_id
$event_id = $_GET['event_id'] ?? 0;

if ($event_id == 0) {
    die("Error: No event selected.");
}

try {
    // 4. 创建一个 Stripe Checkout Session
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card', 'fpx'], // 同时支持信用卡和马来西亚 FPX 银行转账
        'line_items' => [[
            'price_data' => [
                'currency' => 'myr', // 货币：马币
                'product_data' => [
                    'name' => 'Event Registration Fee', // 账单上显示的名字
                    'description' => 'Entry ticket for Event ID: ' . $event_id,
                ],
                'unit_amount' => 5000, // ⚠️ Stripe 的金额单位是 Sen，5000 Sen = RM 50.00
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        // 成功付款后，Stripe 会把用户连同 session_id 一起送回这个页面
        'success_url' => 'https://koo.codex-biz.com/prt_system/payment_success.php?session_id={CHECKOUT_SESSION_ID}&event_id=' . $event_id,
        // 如果用户在付款页面点了返回/取消，就跳回比赛详情页
        'cancel_url' => 'https://koo.codex-biz.com/prt_system/event_details.php?id=' . $event_id,
    ]);

    // 5. 将用户强制重定向 (Redirect) 到 Stripe 漂亮的安全支付页面
    header("Location: " . $session->url);
    exit();

} catch (Exception $e) {
    // 如果出错，把错误信息打印出来方便排查
    echo "Stripe Error: " . $e->getMessage();
}
?>