# products


`composer require controleonline/products:dev-master`



Create a new fila on controllers:
config\routes\controllers\products.yaml

`
controllers:
    resource: ../../vendor/controleonline/products/src/Controller/
    type: annotation      
`

Add to entities:
nelsys-api\config\packages\doctrine.yaml
`
parameters:
    # Adds a fallback DATABASE_URL if the env var is not set.
    # This allows you to run cache:warmup even if your
    # environment variables are not available yet.
    # You should not need to change this value.
    #env(DATABASE_URL): ""
doctrine:
    dbal:
        # configure these for your database server
        #url: '%env(resolve:DATABASE_URL)%'
        #driver_class: Realestate\MssqlBundle\Driver\PDODblib\Driver
        driver: "%env(resolve:db_driver)%"
        host: "%env(resolve:db_host)%"
        dbname: "%env(resolve:db_name)%"
        user: "%env(resolve:db_user)%"
        password: "%env(resolve:db_password)%"
        port: "%env(resolve:db_port)%"
        instancename: "%env(resolve:db_instance)%"
        options:
            TrustServerCertificate: yes
            Encrypt: No            
            Language: "English"          
        default_table_options:
            #charset: utf8mb4
            collate: SQL_Latin1_General_CP850_CI_AI
        mapping_types:
            identificador: string
            descricao: string
            tipo: string
    orm:
        auto_generate_proxy_classes: true
        naming_strategy: doctrine.orm.naming_strategy.underscore
        auto_mapping: true
        mappings:
            App:
                is_bundle: false
                type: annotation
                dir: "%kernel.project_dir%/src/Entity"
                prefix: 'App\Entity'
                alias: App       
            Products:
                is_bundle: false
                type: annotation
                dir: "%kernel.project_dir%/vendor/controleonline/products/src/Entity"
                prefix: 'ControleOnline\Entity'
                alias: App                                
`                
