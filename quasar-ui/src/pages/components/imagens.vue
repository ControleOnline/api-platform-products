<template>
  <div class="img-box q-pa-md col-12">
    <q-carousel animated swipeable thumbnails infinite v-model="slide">
      <q-carousel-slide
        v-for="(image, index) in images"
        v-bind:key="image.id"
        :name="index"
        :img-src="image.src"
      />
    </q-carousel>
  </div>
</template>

<script>
export default {
  props: {
    product: {
      required: true,
    },
  },
  data() {
    return {
      slide: 0,
      images: [
        {
          id: 1,
          src:
            "https://orgbling.s3.amazonaws.com/a1081b4a9b1caa7782ead1d31b20b613/e7a26a1d6048ebb7105882dc686a74bd?AWSAccessKeyId=AKIATCLMSGFX4J7TU445&Expires=1691088579&Signature=bKqk9wrOGi6VUNeIMNBmPQLljlw%3D",
        },
        {
          id: 2,
          src:
            "https://orgbling.s3.amazonaws.com/a1081b4a9b1caa7782ead1d31b20b613/b1acc74f8275c12f93a8da1182a50e5c?AWSAccessKeyId=AKIATCLMSGFX4J7TU445&Expires=1691088688&Signature=XvIcY6ynIS36p94zokpLJyW%2FBso%3D",
        },
      ],
    };
  },
};
</script>
<style>
.img-box > div {
  border: 2px #bcbcbc dotted;
  border-radius: 5px;
}
</style>
