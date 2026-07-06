<?php
namespace Marketing\Application\Commands;

class CreateCampaignCommand
{
    public int $tenantId;
    public string $adAccountId;
    public string $name;
    public string $objective;
    public int $dailyBudget; // in cents
    
    public function __construct(int $tenantId, string $adAccountId, string $name, string $objective, int $dailyBudget)
    {
        $this->tenantId = $tenantId;
        $this->adAccountId = $adAccountId;
        $this->name = $name;
        $this->objective = $objective;
        $this->dailyBudget = $dailyBudget;
    }
}
