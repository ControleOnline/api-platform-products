<?php
namespace ControleOnline\Security;

use ControleOnline\Service\ProductCatalogAccessService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ProductCatalogVoter extends Voter
{
    public function __construct(private ProductCatalogAccessService $access) {}
    protected function supports(string $attribute, mixed $subject): bool
    { return $attribute === 'CATALOG_MANAGE' && is_object($subject) && $this->access->supports($subject); }
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        try { $this->access->assertWrite($subject); return true; }
        catch (AccessDeniedException) { return false; }
    }
}
