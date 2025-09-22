<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposta Aprovada - Nexa Platform</title>
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
        .congratulations {
            font-size: 18px;
            color: #E91E63;
            font-weight: bold;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">NEXA</div>
            <div class="status">💖 PARABÉNS! SEU PERFIL FOI SELECIONADO!</div>
        </div>

        <div class="content">
            <div class="congratulations">💖 Parabéns! Seu perfil foi selecionado!</div>
            
            <p>Parabéns! Você tem a cara da marca e foi selecionada para uma parceria de sucesso! Prepare-se para mostrar todo o seu talento e representar a NEXA com criatividade e profissionalismo. Estamos animados para ver o que você vai criar! Abra o site da NEXA e verifique o seu Chat com a marca.</p>

            <div class="info-box">
                <h3>📋 Detalhes da Proposta</h3>
                <p><strong>Campanha:</strong> {{ $application->campaign->title }}</p>
                <p><strong>Marca:</strong> {{ $application->campaign->brand->name }}</p>
                <p><strong>Orçamento Proposto:</strong> R$ {{ number_format($application->proposed_budget, 2, ',', '.') }}</p>
                <p><strong>Prazo Estimado:</strong> {{ $application->estimated_delivery_days }} dias</p>
                <p><strong>Data de Aprovação:</strong> {{ $application->approved_at->format('d/m/Y H:i') }}</p>
            </div>

            <p>Agora é hora de dar início a uma parceria estratégica com a marca. Acesse o site e confira seu chat com a marca para os próximos passos.</p>

            <a href="{{ config('app.frontend_url', 'http://localhost:5000') }}/creator/applications" class="button" style="color: white;">
                Ver Minhas Propostas
            </a>
        </div>

        <div class="footer">
            <p>Este é um email automático da plataforma Nexa.</p>
            <p>Se você tiver alguma dúvida, entre em contato conosco.</p>
        </div>
    </div>
</body>
</html>
