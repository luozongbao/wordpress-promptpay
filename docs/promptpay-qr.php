<?php
// promptpay_qr.php
require_once 'vendor/autoload.php'; // Make sure to run: composer require kittinan/php-promptpay-qr

$qrCodePath = '';
$payload = '';
$error = '';

if ($_POST) {
    $target = trim($_POST['target'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    
    if (empty($target)) {
        $error = 'Please enter a phone number, tax ID, or e-wallet ID';
    } else {
        try {
            $pp = new \KS\PromptPay();
            $payload = $pp->generatePayload($target, $amount ? floatval($amount) : null);
            
            // Generate QR code file
            $qrCodePath = 'qr_' . time() . '.png';
            $pp->generateQrCode($qrCodePath, $target, $amount ? floatval($amount) : null, 300);
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PromptPay QR Code Generator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        input[type="text"]:focus, input[type="number"]:focus {
            border-color: #4CAF50;
            outline: none;
        }
        button {
            width: 100%;
            padding: 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            background-color: #45a049;
        }
        .qr-container {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .qr-image {
            margin: 20px auto;
            border: 2px solid #ddd;
            border-radius: 5px;
        }
        .payload-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #e8f5e8;
            border-radius: 5px;
            word-break: break-all;
            font-family: monospace;
            font-size: 12px;
        }
        .error {
            color: red;
            margin-top: 10px;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 5px;
        }
        .info {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .download-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .download-btn:hover {
            background-color: #1976D2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🇹🇭 PromptPay QR Code Generator</h1>
        
        <div class="info">
            <strong>Supported formats:</strong><br>
            • Phone: 0899999999 or 089-999-9999<br>
            • Tax ID: 1-2345-67890-12-3<br>
            • e-Wallet ID: 15+ digits
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="target">Phone Number / Tax ID / e-Wallet ID:</label>
                <input type="text" id="target" name="target" 
                       value="<?= htmlspecialchars($_POST['target'] ?? '') ?>" 
                       placeholder="0899999999 or 1-2345-67890-12-3" required>
            </div>

            <div class="form-group">
                <label for="amount">Amount (THB) - Optional:</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0" 
                       value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>" 
                       placeholder="100.00">
            </div>

            <button type="submit">Generate QR Code</button>
        </form>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($qrCodePath && file_exists($qrCodePath)): ?>
            <div class="qr-container">
                <h3>Your PromptPay QR Code</h3>
                <img src="<?= $qrCodePath ?>" alt="PromptPay QR Code" class="qr-image">
                <br>
                <a href="<?= $qrCodePath ?>" download class="download-btn">Download QR Code</a>
                
                <?php if ($payload): ?>
                    <div class="payload-info">
                        <strong>Payload:</strong><br><?= htmlspecialchars($payload) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php
    // Clean up old QR code files (optional)
    $files = glob('qr_*.png');
    foreach ($files as $file) {
        if (filemtime($file) < time() - 3600) { // Delete files older than 1 hour
            unlink($file);
        }
    }
    ?>
</body>
</html>