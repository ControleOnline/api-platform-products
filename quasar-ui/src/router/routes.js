export const routes = [
  {
    path: "/products/",
    component: () =>  import ("@controleonline/quasar-layout-ui/src/layouts/AdminLayout.vue"),
    children: [
      {
        name: "ProductList",
        path: "",
        component: () =>  import ("../pages/list.vue"),
      },
    ],
  },
  {
    path: "/product/",
    component: () =>  import ("@controleonline/quasar-layout-ui/src/layouts/AdminLayout.vue"),
    children: [
      {
        name: "ProductDetails",
        path: "id/:id",
        component: () =>  import ("../pages/details.vue"),
      },
      {
        name: "ProductNew",
        path: "new",
        component: () =>  import ("../pages/details.vue"),
      },
    ],
  },
];
