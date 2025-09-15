<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campanha Aprovada - Nexa Platform</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #E91E63;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #E91E63;
            margin-bottom: 10px;
        }
        .status {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-block;
            font-weight: bold;
        }
        .content {
            margin: 30px 0;
        }
        .info-box {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #E91E63;
        }
        .button {
            display: inline-block;
            background-color: #E91E63;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">NEXA</div>
            <div class="status">✅ CAMPANHA APROVADA</div>
        </div>

        <div class="content">
            <h2>Parabéns, {{ $brand->name }}! 🎉</h2>
            
            <p>Sua campanha foi <strong>aprovada</strong> com sucesso!</p>

            <div class="info-box">
                <h3>📋 Detalhes da Campanha</h3>
                <p><strong>Título:</strong> {{ $campaign->title }}</p>
                <p><strong>Orçamento:</strong> R$ {{ number_format($campaign->budget, 2, ',', '.') }}</p>
                <p><strong>Categoria:</strong> {{ $campaign->category }}</p>
                <p><strong>Tipo:</strong> {{ $campaign->campaign_type }}</p>
                <p><strong>Data de Aprovação:</strong> {{ $campaign->approved_at ? (is_string($campaign->approved_at) ? $campaign->approved_at : $campaign->approved_at->format('d/m/Y H:i')) : 'N/A' }}</p>
            </div>

            <p>Agora sua campanha está ativa e os criadores podem começar a se candidatar. Você receberá notificações quando houver novas propostas.</p>

            <a href="{{ config('app.frontend_url', 'http://localhost:5000') }}/brand/campaigns" class="button">
                Ver Minhas Campanhas
            </a>
        </div>

        <div class="footer">
            <p>Este é um email automático da plataforma Nexa.</p>
            <p>Se você tiver alguma dúvida, entre em contato conosco.</p>
        </div>
    </div>
</body>
</html>
