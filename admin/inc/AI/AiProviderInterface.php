<?php
namespace AI;

interface AiProviderInterface {
    public function getName();
    public function generate(string $prompt, string $model, array $options = []);
    public function isHealthy(): bool;
}
