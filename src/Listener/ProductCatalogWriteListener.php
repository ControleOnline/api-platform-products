<?php
namespace ControleOnline\Listener;

use ControleOnline\Entity\{ProductInventory, Inventory};
use ControleOnline\Service\ProductCatalogAccessService;
use Doctrine\ORM\Event\OnFlushEventArgs;

/** Guards direct service/import writes and cascades, including original owners. */
final class ProductCatalogWriteListener
{
    public function __construct(private ProductCatalogAccessService $access) {}
    public function onFlush(OnFlushEventArgs $event): void
    {
        $unit = $event->getObjectManager()->getUnitOfWork();
        $updates = $unit->getScheduledEntityUpdates();
        foreach ([...$unit->getScheduledEntityInsertions(), ...$updates, ...$unit->getScheduledEntityDeletions()] as $entity) {
            // Only derived quantity counters may change under order authorization; ownership/configuration never bypasses catalog rights.
            if ($entity instanceof ProductInventory && in_array($entity, $updates, true)) {
                $fields = array_keys($unit->getEntityChangeSet($entity));
                if ($fields && array_diff($fields, ['available', 'sales', 'purchases', 'transit']) === []) {
                    $this->access->assertReadCompany($entity->getProduct()?->getCompany());
                    $this->access->assertReadCompany($entity->getInventory()?->getPeople());
                    continue;
                }
            }
            if ($this->access->supports($entity)) $this->access->assertWrite($entity, $unit->getOriginalEntityData($entity));
        }
    }
}
