<template>
  <q-page>
    <!-- Inicio das Abas -->
    <div class="q-pt-lg">
      <q-card>
        <q-card-section>
          <Product :product="productData" class="q-card q-pa-sm" />
        </q-card-section>
        <q-card-section v-if="idProduct">
          <div class="q-card q-pa-sm" style="max-width: calc(100vw - 30px)">
            <q-tabs inline-label no-caps outside-arrows mobile-arrows align="left" class="bg-primary text-white shadow-2"
              v-model="tab">
              <q-tab name="group" icon="tab" :label="$t('Group')" />
              <q-tab name="categories" icon="tab" :label="$t('Categories')" />
              <q-tab name="price" icon="tab" :label="$t('Price')" />
              <q-tab name="provider" icon="tab" :label="$t('Provider')" />
              <q-tab name="stock" icon="tab" :label="$t('Stock')" />
              <q-tab name="advertisement" icon="tab" :label="$t('Advertisement')" />
              <q-tab name="taxing" icon="tab" :label="$t('Taxing')" />
            </q-tabs>

            <q-tab-panels v-model="tab" animated swipeable transition-prev="jump-up" transition-next="jump-up">
              <q-tab-panel class="items-center" name="group">
                <Group :product="productData" />
              </q-tab-panel>
              <q-tab-panel class="items-center" name="categories">
                <Categories :product="productData" />
              </q-tab-panel>
              <q-tab-panel class="items-center" name="price">
                <Price :product="productData" />
              </q-tab-panel>
              <q-tab-panel class="items-center" name="provider">
                <Provider :product="productData" />
              </q-tab-panel>
              <q-tab-panel class="items-center" name="stock">
                <Stock :product="productData" />
              </q-tab-panel>
              <q-tab-panel class="items-center" name="advertisement">
                <Advertisement :product="productData" />
              </q-tab-panel>
              <q-tab-panel class="items-center" name="taxing">
                <Taxing :product="productData" />
              </q-tab-panel>
            </q-tab-panels>
          </div>
        </q-card-section>
      </q-card>
    </div>
  </q-page>
</template>

<script>
import { mapGetters, mapActions } from "vuex";
import Product from "./components/details.vue";
import Advertisement from "./components/tabs/advertisement.vue";
import Categories from "./components/tabs/categories.vue";
import Group from "./components/tabs/group.vue";
import Price from "./components/tabs/price.vue";
import Provider from "./components/tabs/provider.vue";
import Stock from "./components/tabs/stock.vue";
import Taxing from "./components/tabs/taxing.vue";

export default {
  components: {
    Product,
    Group,
    Categories,
    Price,
    Provider,
    Stock,
    Advertisement,
    Taxing,
  },

  data() {
    return {
      productData: {
        id: null,
        product: null,
        sku: null,
        type: null,
        price: null,
        productUnit: { productUnit: null },
        productCondition: null,
        active: null,
        description: '',
      },
      idProduct: null,
      tab: "group",
    };
  },
  created() {
    let idProduct = decodeURIComponent(this.$route.params.id);
    this.idProduct = idProduct == 'undefined' ? null : idProduct;
    this.getData();
  },

  computed: {
    ...mapGetters({
      myCompany: "people/currentCompany",
      isLoading: "products/isLoading",
    }),
  },

  methods: {
    ...mapActions({
      getProduct: "products/get",
    }),
    getData() {
      if (this.idProduct)
        this.getProduct( 
          this.idProduct        
        ).then((data) => {
          this.productData = data;
        });
    }
  },

  watch: {

  },
};
</script>
