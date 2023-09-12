<template>
  <DefaultTable :configs="configs" v-if="configs" />
</template>
<script>
import DefaultTable from "@controleonline/quasar-common-ui/src/components/Default/DefaultTable";
export default {
  name: "Products",
  components: {
    DefaultTable
  },

  computed: {
    configs() {
      return {
        module: 'products',
        selection: false,
        search: false,
        components: {
          //acoes: () => import("@nelsys/../../src/modules/nelsys/quasar-waybill-ui/src/components/pedido/acoes.vue"),
          //componentProps: {
          //   x: 1,
          //    Y: 2
          //}
        },
        list: {
          productType: this.productType,
          productConditions: this.productConditions,
        },
      };
    }
  },
  data() {
    return {

      productType: [
        { label: this.$t('product.product'), value: 'product' },
        { label: this.$t('product.service'), value: 'service' },
        { label: this.$t('product.component'), value: 'component' }
      ],
      productConditions: [
        { label: this.$t('product.new'), value: 'new' },
        { label: this.$t('product.used'), value: 'used' },
        { label: this.$t('product.recondicioned'), value: 'recondicioned' }
      ],

    };
  },
  created() {

  },
  methods: {

  },
};
</script>