<template>
  <div class="flex-container">
    <div class="flex-street">
      <label class="flex-street-label inline-block pt-2 leading-tight streetAddress">
        {{ streetAddress }}
      </label>
    </div>
    <div class="flex-latitude">
      <label class="inline-block pt-2 leading-tight"> Latitude </label>
      <input @input="updateLatLng(lat, lng)" v-model="lat" type="text" :disabled="!edit"
        :class="{ 'form-input-black': edit }" class="form-control form-input form-input-bordered shadow-lg"
        v-on:keydown.enter.prevent=preventEnterPropagation />
    </div>
    <div class="flex-longitude">
      <label class="inline-block pt-2 leading-tight"> Longitude </label>
      <input @input="updateLatLng(lat, lng)" v-model="lng" type="text" :disabled="!edit"
        :class="{ 'form-input-black': edit }" class="form-control form-input form-input-bordered shadow-lg"
        v-on:keydown.enter.prevent=preventEnterPropagation />
    </div>
    <div class="flex-street" v-if="edit">
      <input @input="updateStreetAddress($event)" type="text"
        class="form-input-black flex-street-input form-control form-input form-input-bordered shadow-lg"
        placeholder="Search by Address" v-on:keydown.enter.prevent=preventEnterPropagation />
    </div>
  </div>
  <div id="container">
    <div :id="mapRef" class="wm-map"></div>
  </div>
</template>

<style scoped>
.form-input-black:focus {
  border-color: grey;
}

.form-input {
  width: 100%;
}

.flex-container {
  margin: 10px 10px 10px 0px;
  width: 600px;
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  justify-content: center;
  align-content: flex-start;
  align-items: stretch;
}

.flex-latitude {
  flex: 1 0 25%;
  align-self: auto;
  margin-right: 5px;
}

.flex-longitude {
  flex: 1 0 25%;
  align-self: auto;
}

.flex-street {
  display: flex;
  flex-direction: row;
  justify-content: flex-start;
  align-content: flex-start;
  align-items: flex-start;
  margin-top: 10px;
  flex: 1 0 100%;
  align-self: auto;
}

.flex-street-label {
  flex: 0 0 auto;
  align-self: auto;
}

.flex-street-input {
  flex: 1 0 auto;
  align-self: auto;
}

.inline-block {
  margin-right: 5px;
}

.streetAddress {
  color: rgba(var(--colors-primary-500));
}
</style>

<script>
import "leaflet/dist/leaflet.css";
import "leaflet.fullscreen/Control.FullScreen.js";
import "leaflet.fullscreen/Control.FullScreen.css";
import L from "leaflet";
import axios from "axios";
import { FormField, HandlesValidationErrors } from "laravel-nova";

const DEFAULT_TILES = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
const VERSION = "0.0.9"
const VERSION_IMAGE = `<img class="version-image" src="https://img.shields.io/badge/wm--map--point-${VERSION}-blue">`;
const DEFAULT_ATTRIBUTION =
  '<a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery (c) <a href="https://www.mapbox.com/">Mapbox</a>';
const DEFAULT_CENTER = [42, 12];
const DEFAULT_MINZOOM = 8;
const DEFAULT_MAXZOOM = 17;
const DEFAULT_DEFAULTZOOM = 8;

export default {
  name: "Map",
  mixins: [FormField, HandlesValidationErrors],
  props: ["field", "edit"],
  data() {
    return {
      mapRef: `mapContainer-${Math.floor(Math.random() * 10000 + 10)}`,
      lat: null,
      lng: null,
      currentCircle: null,
      circleOption: {
        color: "red",
        fillColor: "#f03",
        fillOpacity: 1,
        radius: 20
      },
      mapDiv: null,
      streetAddress: null,
      center: null,
      deleteIcon: null,
      myZoom: {
        start: 0,
        end: 0
      },
      maxZoom: null,
      minZoom: null,
      attribution: null,
      radius: 20
    };
  },
  methods: {
    initMap() {
      setTimeout(() => {
        this.center = this.field.center ?? DEFAULT_CENTER;
        this.maxZoom = this.field.maxZoom ?? DEFAULT_MAXZOOM;
        this.minZoom = this.field.minZoom ?? DEFAULT_MINZOOM;
        this.attribution = this.field.attribution ?? DEFAULT_ATTRIBUTION;
        this.buildMap();
        this.myZoom = {
          start: this.map.getZoom(),
          end: this.map.getZoom()
        };
        this.buildDeleteGeometry();
        if (this.field.latlng != null && this.field.latlng[0] != null && this.field.latlng[1] != null) {
          this.currentCircle = L.circle(this.field.latlng, this.circleOption).addTo(
            this.map
          );
        } else {
          this.deleteIcon.style.visibility = "hidden";
        }
        if (this.edit) {
          this.map.on("click", this.mapClick);
          this.map.on("zoomstart", () => {
            this.myZoom.start = this.map.getZoom();
          });
          this.map.on("zoomend", () => {
            this.myZoom.end = this.map.getZoom();
            var diff = this.myZoom.start - this.myZoom.end;
            if (diff > 0) {
              this.currentCircle.setRadius(this.currentCircle.getRadius() * 2);
            } else if (diff < 0) {
              this.currentCircle.setRadius(this.currentCircle.getRadius() / 2);
            }
          });
        } else {
          this.map.doubleClickZoom.disable();
          this.deleteIcon.style.visibility = "hidden";
        }
      }, 300);
    },
    mapClick(e) {
      this.updateLatLng(e.latlng.lat, e.latlng.lng);
      this.deleteIcon.style.visibility = "visible";
    },
    buildMap() {
      const defaultZoom = this.field.defaultZoom ?? DEFAULT_DEFAULTZOOM;
      var currentView = this.center;
      if (this.field.latlng != null && this.field.latlng[0] != null && this.field.latlng[1] != null) {
        this.lat = this.field.latlng[0];
        this.lng = this.field.latlng[1];
        this.reverseGeoCoding(this.lat, this.lng);
        currentView = this.field.latlng;
      }
      this.map = L.map(this.mapRef, {
        fullscreenControl: true,
        fullscreenControlOptions: {
          position: "topleft"
        }
      }).setView(currentView, defaultZoom);
      L.tileLayer(this.field.tiles ?? DEFAULT_TILES, {
        attribution: `${this.attribution}, ${VERSION_IMAGE}`,
        maxZoom: this.maxZoom,
        minZoom: this.minZoom,
        id: "mapbox/streets-v11"
      }).addTo(this.map);
    },
    buildDeleteGeometry() {
      L.Control.deleteGeometry = L.Control.extend({
        onAdd: () => {
          this.deleteIcon = L.DomUtil.create('div')
          L.DomUtil.addClass(this.deleteIcon, 'delete-button');
          var img = L.DomUtil.create('img');
          img.src = 'https://cdn-icons-png.flaticon.com/512/2891/2891491.png';
          this.deleteIcon.appendChild(img);
          L.DomEvent.on(this.deleteIcon, "click", (e) => {
            L.DomEvent.stopPropagation(e);
            this.updateLatLng(null, null);
            this.deleteIcon.style.visibility = "hidden";
          });
          return this.deleteIcon;
        }
      });
      L.control.deleteGeometry = function (opts) {
        return new L.Control.deleteGeometry(opts);
      }
      L.control.deleteGeometry({ position: 'topright' }).addTo(this.map);
    },
    updateLatLng(lat, lng) {
      let currentRadius = 20;
      if (this.currentCircle != null) {
        currentRadius = this.currentCircle.getRadius();
        this.map.removeLayer(this.currentCircle);
      }
      if (lat != null && lng != null) {
        this.currentCircle = new L.circle(
          { lat, lng },
          { ...this.circleOption, ...{ radius: currentRadius } }
        ).addTo(this.map);
        this.map.panTo(new L.LatLng(lat, lng));
        this.map.setView([lat, lng], this.maxZoom);
        this.reverseGeoCoding(lat, lng);
      } else {
        this.map.panTo(new L.LatLng(this.center[0], this.center[1]));
      }
      this.lat = lat;
      this.lng = lng;
      this.$emit("latlng", [lat, lng]);
    },
    async updateStreetAddress(event) {
      clearTimeout(this.debounce);
      this.debounce = setTimeout(async () => {
        try {
          var res = await axios.get(
            `https://nominatim.openstreetmap.org/search.php?q=${event.target.value}&polygon_geojson=1&format=jsonv2`
          );
          const lat = res.data[0].lat;
          const lng = res.data[0].lon;
          this.updateLatLng(lat, lng);
        } catch (_) {
        }
      }, 1000);
    },
    async reverseGeoCoding(lat, lng) {
      var api = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`;
      try {
        var res = await axios.get(api);
        this.streetAddress = res.data.display_name;
      } catch (_) {
      }
    }
  },
  preventEnterPropagation: function (e) {
    if (e) e.preventDefault();
  },
  mounted() {
    this.initMap();
  }
};
</script>
