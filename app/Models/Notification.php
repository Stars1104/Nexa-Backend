<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Methods
    public function markAsRead(): bool
    {
        return $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function markAsUnread(): bool
    {
        return $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    // Static methods for creating notifications
    public static function createLoginDetected($userId, $loginData = []): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'login_detected',
            'title' => 'Novo Login Detectado',
            'message' => 'Um novo login foi detectado na sua conta. Se não foi você, por favor, proteja sua conta imediatamente.',
            'data' => $loginData,
        ]);
    }

    public static function createNewUserRegistration($userId, $registrationData = []): self
    {
        $userName = $registrationData['user_name'] ?? 'Usuário Desconhecido';
        $userRole = $registrationData['user_role'] ?? 'Papel Desconhecido';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'new_user_registration',
            'title' => 'Novo Registro de Usuário',
            'message' => "Novo {$userRole} registrado: {$userName}",
            'data' => $registrationData,
        ]);
    }

    public static function createNewCampaign($userId, $campaignData = []): self
    {
        $brandName = $campaignData['brand_name'] ?? 'Marca Desconhecida';
        $campaignTitle = $campaignData['campaign_title'] ?? 'Campanha Desconhecida';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'new_campaign',
            'title' => 'Nova Campanha Criada',
            'message' => "{$brandName} postou nova campanha: {$campaignTitle}",
            'data' => $campaignData,
        ]);
    }

    public static function createNewApplication($userId, $applicationData = []): self
    {
        $creatorName = $applicationData['creator_name'] ?? 'Criador Desconhecido';
        $campaignTitle = $applicationData['campaign_title'] ?? 'Campanha Desconhecida';
        $brandName = $applicationData['brand_name'] ?? 'Marca Desconhecida';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'new_application',
            'title' => 'Nova Campanha Aplicada',
            'message' => "{$creatorName} aplicou para a campanha de {$brandName}: {$campaignTitle}",
            'data' => $applicationData,
        ]);
    }

    public static function createNewBid($userId, $bidData = []): self
    {
        $campaignTitle = $bidData['campaign_title'] ?? 'Campanha Desconhecida';
        $creatorName = $bidData['creator_name'] ?? 'Criador Desconhecido';
        $bidAmount = $bidData['bid_amount'] ?? 'Valor Desconhecido';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'new_bid',
            'title' => 'Nova Proposta Recebida',
            'message' => "Nova proposta de {$creatorName} para '{$campaignTitle}' - R$ {$bidAmount}",
            'data' => $bidData,
        ]);
    }

    public static function createPaymentActivity($userId, $paymentData = []): self
    {
        $paymentType = $paymentData['payment_type'] ?? 'Tipo de Pagamento Desconhecido';
        $userName = $paymentData['user_name'] ?? 'Usuário Desconhecido';
        $amount = $paymentData['amount'] ?? 'Valor Desconhecido';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'payment_activity',
            'title' => 'Atividade de Pagamento',
            'message' => "{$paymentType} de {$userName} - {$amount}",
            'data' => $paymentData,
        ]);
    }

    public static function createPortfolioUpdate($userId, $portfolioData = []): self
    {
        $userName = $portfolioData['user_name'] ?? 'Usuário Desconhecido';
        $updateType = $portfolioData['update_type'] ?? 'Tipo de Atualização Desconhecido';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'portfolio_update',
            'title' => 'Atualização de Portfólio',
            'message' => "{$userName} atualizou seu portfólio: {$updateType}",
            'data' => $portfolioData,
        ]);
    }

    public static function createSystemActivity($userId, $activityData = []): self
    {
        $activityType = $activityData['activity_type'] ?? 'Atividade Desconhecida';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'system_activity',
            'title' => 'Atividade do Sistema',
            'message' => "Atividade do sistema detectada: {$activityType}",
            'data' => $activityData,
        ]);
    }

    public static function createNewProject($userId, $campaignId, $campaignTitle): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'new_project',
            'title' => 'Novo Projeto Disponível',
            'message' => "Um novo projeto '{$campaignTitle}' está agora disponível para aplicações.",
            'data' => [
                'campaign_id' => $campaignId,
                'campaign_title' => $campaignTitle,
            ],
        ]);
    }

    public static function createProjectApproved($userId, $campaignId, $campaignTitle): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'project_approved',
            'title' => 'Projeto Aprovado',
            'message' => "Seu projeto '{$campaignTitle}' foi aprovado e está agora ativo.",
            'data' => [
                'campaign_id' => $campaignId,
                'campaign_title' => $campaignTitle,
            ],
        ]);
    }

    public static function createProjectRejected($userId, $campaignId, $campaignTitle, $reason = null): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'project_rejected',
            'title' => 'Projeto Rejeitado',
            'message' => "Seu projeto '{$campaignTitle}' não foi aprovado." . ($reason ? " Motivo: {$reason}" : ''),
            'data' => [
                'campaign_id' => $campaignId,
                'campaign_title' => $campaignTitle,
                'rejection_reason' => $reason,
            ],
        ]);
    }

    public static function createProposalApproved($userId, $campaignId, $campaignTitle, $brandName): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'proposal_approved',
            'title' => 'Proposta Aprovada',
            'message' => "Sua proposta para '{$campaignTitle}' foi aprovada por {$brandName}.",
            'data' => [
                'campaign_id' => $campaignId,
                'campaign_title' => $campaignTitle,
                'brand_name' => $brandName,
            ],
        ]);
    }

    public static function createProposalRejected($userId, $campaignId, $campaignTitle, $brandName, $reason = null): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'proposal_rejected',
            'title' => 'Proposta Rejeitada',
            'message' => "Sua proposta para '{$campaignTitle}' não foi selecionada por {$brandName}." . ($reason ? " Motivo: {$reason}" : ''),
            'data' => [
                'campaign_id' => $campaignId,
                'campaign_title' => $campaignTitle,
                'brand_name' => $brandName,
                'rejection_reason' => $reason,
            ],
        ]);
    }

    public static function createNewMessage($userId, $senderId, $senderName, $messagePreview, $chatType = 'direct'): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'new_message',
            'title' => 'Nova Mensagem',
            'message' => "Você tem uma nova mensagem de {$senderName}: {$messagePreview}",
            'data' => [
                'sender_id' => $senderId,
                'sender_name' => $senderName,
                'message_preview' => $messagePreview,
                'chat_type' => $chatType,
            ],
        ]);
    }

    public static function createContractTerminated($userId, $contractData = []): self
    {
        $reason = $contractData['reason'] ?? 'Contrato encerrado';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'contract_terminated',
            'title' => 'Contrato Encerrado',
            'message' => "Contrato foi encerrado: {$reason}",
            'data' => $contractData,
        ]);
    }

    public static function createPaymentAvailable($userId, $paymentData = []): self
    {
        $creatorAmount = $paymentData['creator_amount'] ?? 0;
        
        return self::create([
            'user_id' => $userId,
            'type' => 'payment_available',
            'title' => 'Pagamento Disponível',
            'message' => "Pagamento de R$ {$creatorAmount} está agora disponível para saque",
            'data' => $paymentData,
        ]);
    }
} 