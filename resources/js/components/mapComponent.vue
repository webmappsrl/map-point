<template>
  <div class="flex-container">
    <div class="flex-latitude">
        <label class="inline-block pt-2 leading-tight">
          Latitude
        </label>
        <input @input="updateLatLng(lat,lng)" v-model="lat" type="text" :disabled="!edit" :class="{ 'form-input-black': edit }" class="form-control form-input form-input-bordered shadow-lg">
    </div>
    <div class="flex-longitude">
        <label class="inline-block pt-2 leading-tight">
          Longitude
        </label>
        <input @input="updateLatLng(lat,lng)" v-model="lng" type="text" :disabled="!edit" :class="{ 'form-input-black': edit }" class="form-control form-input form-input-bordered shadow-lg">
    </div>
    <div class="flex-street">
        <label class="flex-street-label inline-block pt-2 leading-tight">
            Street Name
        </label>
        <input type="text" :disabled="!edit" :class="{ 'form-input-black': edit }" class="flex-street-input form-control form-input form-input-bordered shadow-lg">
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
.flex-latitude{
    flex: 1 0 25%;
    align-self: auto;
}

.flex-longitude{
    flex: 1 0 25%;
    align-self: auto;
}

.flex-street{
    display: flex;
    flex-direction: row;
    justify-content: flex-start;
    align-content: flex-start;
    align-items: flex-start;
    margin-top: 10px;

    flex: 1 0 100%;
    align-self: auto;
}
.flex-street-label{
    flex: 0 0 auto;
    align-self: auto;
}

.flex-street-input{
    flex: 1 0 auto;
    align-self: auto;
}
.inline-block {
    margin-right: 5px;
}
</style>

<script>
import { FormField, HandlesValidationErrors } from 'laravel-nova'
import "leaflet/dist/leaflet.css";
import L from "leaflet";
const DEFAULT_TILES = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const DEFAULT_ATTRIBUTION = '<a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery (c) <a href="https://www.mapbox.com/">Mapbox</a>';
const DEFAULT_CENTER = [42, 12];
const DEFAULT_MINZOOM = 8;
const DEFAULT_MAXZOOM = 17;
const DEFAULT_DEFAULTZOOM = 8;
export default {
    name: "Map",
    mixins: [FormField, HandlesValidationErrors],
    props: ['field', 'edit'],
    data() {
        return {
            mapRef: `mapContainer-${Math.floor(Math.random() * 10000 + 10)}`,
            lat:null,
            lng:null,
            currentCircle:null,
            circleOption: {
                color: 'red',
                fillColor: '#f03',
                fillOpacity: 1,
                radius: 100
            },
            mapDiv:null
        }
    },
    methods: {
      initMap() {
            setTimeout(() => {
                if (this.field.latlng !== undefined && this.field.latlng.length != 0) {
                    var center = this.field.latlng;
                } else if (this.field.center !== undefined && this.field.latlng.center != 0) {
                    var center = this.field.center;
                } else {
                    var center = DEFAULT_CENTER;
                }
                const defaultZoom = this.field.defaultZoom ?? DEFAULT_DEFAULTZOOM;
                this.map = L.map(this.mapRef).setView(center, defaultZoom);
                const myZoom = {
                    start: this.map.getZoom(),
                    end: this.map.getZoom()
                };

                L.tileLayer(
                    this.field.tiles ?? DEFAULT_TILES,
                    {
                        attribution: this.field.attribution ?? DEFAULT_ATTRIBUTION,
                        maxZoom: this.field.maxZoom ?? DEFAULT_MAXZOOM,
                        minZoom: this.field.minZoom ?? DEFAULT_MINZOOM,
                        id: "mapbox/streets-v11",
                    }
                ).addTo(this.map);
                this.currentCircle = L.circle(center, this.circleOption).addTo(this.map);
                this.lat = center[0];
                this.lng = center[1];
                if (this.edit) {
                    this.map.on('click', (e) => {
                        this.updateLatLng(e.latlng.lat,e.latlng.lng);
                    });
                    this.map.on('zoomstart', function () {
                        myZoom.start = this.map.getZoom();
                    });
                    this.map.on('zoomend', function () {
                        myZoom.end = this.map.getZoom();
                        var diff = myZoom.start - myZoom.end;
                        if (diff > 0) {
                            this.currentCircle.setRadius(this.currentCircle.getRadius() * 2);
                        } else if (diff < 0) {
                            this.currentCircle.setRadius(this.currentCircle.getRadius() / 2);
                        }
                    });
                } else {
                    this.map.dragging.disable();
                    this.map.zoomControl.remove()
                    this.map.scrollWheelZoom.disable();
                    this.map.doubleClickZoom.disable();
                }
            }, 300);

        },
        updateLatLng(lat,lng) {
            const currentRadius = this.currentCircle.getRadius();
            this.map.removeLayer(this.currentCircle);
            this.currentCircle = new L.circle({lat,lng}, { ...this.circleOption, ...{ radius: currentRadius } }).addTo(this.map);
            this.$emit('latlng', [lat, lng]);
            this.lat = lat;
            this.lng = lng;
            this.map.panTo(new L.LatLng(lat,lng));
        }
    },
    mounted() {
        this.initMap();
    },
};
</script>
