
<template>
      <div class="flex flex-col md:flex-row" index="1">
        <div class="px-6 md:px-8 mt-2 md:mt-0 w-full md:w-1/5 md:py-5">
          <label for="user-update-ec-poi-100-belongs-to-field" class="inline-block pt-2 leading-tight">
            Lng
          </label>
        </div>
        <div class="mt-1 md:mt-0 pb-5 px-6 md:px-8 md:w-3/5 w-full md:py-5">
          <div class="flex items-center space-x-2"><!---->
            <div class="flex relative w-full"> 
              <input @input="updateLatLng(lat,lng)" v-model="lng" type="text" class="w-full form-control form-input form-input-bordered">
            </div>
          </div>
        </div>
      </div>
      <div class="flex flex-col md:flex-row" index="1">
        <div class="px-6 md:px-8 mt-2 md:mt-0 w-full md:w-1/5 md:py-5">
          <label for="user-update-ec-poi-100-belongs-to-field" class="inline-block pt-2 leading-tight">
            Lat
          </label>
        </div>
        <div class="mt-1 md:mt-0 pb-5 px-6 md:px-8 md:w-3/5 w-full md:py-5">
          <div class="flex items-center space-x-2"><!---->
            <div class="flex relative w-full"> 
              <input @input="updateLatLng(lat,lng)" v-model="lat" type="text" class="w-full form-control form-input form-input-bordered">
            </div>
          </div>
        </div>
      </div>

    <div id="container">
        <div :id="mapRef" class="wm-map"></div>
    </div>
</template>

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
