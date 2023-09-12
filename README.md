# products


`composer require controleonline/products:dev-master`



Create a new fila on controllers:
config\routes\controllers\products.yaml

```yaml
controllers:
    resource: ../../vendor/controleonline/products/src/Controller/
    type: annotation      
```

Add to entities:
nelsys-api\config\packages\doctrine.yaml
```yaml
doctrine:
    orm:
        mappings:
           Products:
                is_bundle: false
                type: annotation
                dir: "%kernel.project_dir%/vendor/controleonline/products/src/Entity"
                prefix: 'ControleOnline\Entity'
                alias: App                             
```          


Add this line on your routes:
config\packages\api_platform.yaml
```yaml          
mapping   :
    paths: ['%kernel.project_dir%/src/Entity','%kernel.project_dir%/src/Resource',"%kernel.project_dir%/vendor/controleonline/products/src/Entity"]        
```          
