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
    /**
     * The function initMap() is used for initializing a map. It sets certain properties of the map based on the field object passed in. 
     * If some of these properties are not defined, default values are used. 
     * A timeout of 300 milliseconds is set before the function executes, which allows the map to properly load before any changes are made to it. 
     * The function performs the following tasks in order:
     * Sets the center of the map to the value of this.field.center, or DEFAULT_CENTER if this.field.center is not defined.
     * Sets the maximum zoom level of the map to the value of this.field.maxZoom, or DEFAULT_MAXZOOM if this.field.maxZoom is not defined.
     * Sets the minimum zoom level of the map to the value of this.field.minZoom, or DEFAULT_MINZOOM if this.field.minZoom is not defined.
     * Sets the attribution for the map to the value of this.field.attribution, or DEFAULT_ATTRIBUTION if this.field.attribution is not defined.
     * Calls the buildMap() function to build the map with the specified properties.
     * Sets myZoom to an object with start and end properties, both set to the current zoom level of the map.
     * Calls the buildDeleteGeometry() function.
     * If this.field.latlng is defined and its values are not null, a circle with options this.circleOption is created at the specified latlng value and added to the map. 
     * Otherwise, the deleteIcon's visibility is set to "hidden".
     * If this.edit is truthy, event listeners are attached to the map for click, zoomstart, and zoomend events. 
     * On zoom events, the radius of the currentCircle is modified based on the difference between the start and end zoom levels. 
     * Otherwise, double-click zoom is disabled and the visibility of the deleteIcon is set to "hidden".
     */
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
    /**
     * @param {*} e
     * The code defines a function mapClick which takes an event object (e) as input. 
     * The function first calls another function called updateLatLng with two arguments, namely the lat and lng properties of the latlng object nested inside the input e object. 
     * This updateLatLng function likely updates some UI element(s) to display the new latitude and longitude. 
     * The function then sets the CSS property visibility of a particular deleteIcon element to "visible". 
     * This suggests that the deleteIcon is hidden by default and only made visible after the user clicks on the map. No value is returned by the function.
     */
    mapClick(e) {
      this.updateLatLng(e.latlng.lat, e.latlng.lng);
      this.deleteIcon.style.visibility = "visible";
    },
    /**
     * This is a function called buildMap() that creates a Leaflet map.
     * It first sets the default zoom level, using the nullish coalescing operator, which takes the value of this.field.defaultZoom if it's not null, and DEFAULT_DEFAULTZOOM otherwise.
     * It then sets the currentView as the center of the map by default.
     * It checks if the field object has a latlng array, and if it's not null or undefined, it sets the lat and lng properties of the map to the values in the latlng array, 
     * then uses reverseGeocoding() function to get the address for these coordinates. It also sets the currentView to the latlng coordinates.
     * It creates a Leaflet map object, and stores it in the map property of the object.
     * It adds a tile layer to the map, using the L.tileLayer() method, with the field.tiles URL or a default one, DEFAULT_TILES, along with attribution and version image. 
     * Finally, it adds this layer to the map by calling the addTo() method on the map object.
     */
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
    /**
     * The code defines a new Leaflet control called 'deleteGeometry' that is added to the map at the top right position. 
     * When clicked, this control deletes any drawn geometry and hides itself. 
     * The 'deleteGeometry' control is defined through the use of a Leaflet Control.extend method. 
     * The control's onAdd function is defined to return a div element with a delete button represented by an image.
     * When the delete button is clicked, a DOM event listener stops the event from propagating, updates the latitude and longitude coordinates of the drawn geometry to null, and hides the delete button.
     * The control is then instantiated with a position option of 'topright' and added to the Leaflet map.
     */
    buildDeleteGeometry() {
      // Extend the Leaflet Control class to create a custom deleteGeometry control
      L.Control.deleteGeometry = L.Control.extend({
        onAdd: () => {
          this.deleteIcon = L.DomUtil.create('div')
          L.DomUtil.addClass(this.deleteIcon, 'delete-button');
          var img = L.DomUtil.create('img');
          img.src = 'https://cdn-icons-png.flaticon.com/512/2891/2891491.png';
          this.deleteIcon.appendChild(img);
          // Add a click event listener to the delete button
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
    /**
     * @param {*} lat 
     * @param {*} lng 
     * This method is used to update the latitude and longitude in a Leaflet map with a circle overlay. It takes in two parameters: lat for latitude and lng for longitude. 
     * First, it initializes a variable currentRadius to 20. If there is already a circle overlay on the map (this.currentCircle is not null), 
     * it sets currentRadius to either the radius of the current circle or 20, whichever is smaller. 
     * It also removes the current circle overlay from the map using the removeLayer method.
     * Next, it creates a new circle overlay using the lat and lng coordinates, and sets its radius using currentRadius. 
     * It adds this new circle overlay to the map and pans to the location using the panTo method. It also sets the view of the map to the new location using setView. 
     * Finally, it calls the reverseGeoCoding method to perform reverse geocoding (converting the coordinates to an address).
     * If lat or lng is null, it pans to the center coordinates (this.center) of the map.
     * Finally, it sets the lat and lng properties of the component to the new values, and emits a latlng event with an array of [lat, lng].
     */
    updateLatLng(lat, lng) {
      let currentRadius = 20;
      if (this.currentCircle != null) {
        currentRadius = this.currentCircle.getRadius() > 20 ? 20 : this.currentCircle.getRadius();
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
    /**
     * @param {*} event 
     * The code is an async function named updateStreetAddress that takes an event object as its parameter. 
     * It starts by clearing a previously set timeout using the clearTimeout function.
     * A new timeout is then defined using setTimeout with a delay of 1000 milliseconds which executes the callback function asynchronously. 
     * The callback function makes an HTTP GET request to an API endpoint exposed through the axios library using a URL string that includes the event.target.value parameter value. 
     * The API query parameters include search query q (event.target.value) and output format parameters polygon_geojson=1 and format=jsonv2.
     * If the API query returns successfully, the response object res has the first object's latitude and longitude values extracted and stored as lat and lng respectively. 
     * The updateLatLng function is then called with arguments lat and lng to update the data model with these new values.
     * If the API query doesn't return a success response, the catch block catches the error and does nothing.
     * The function ultimately returns nothing since it does not have an explicit return statement.
     */
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
    /**
     * @param {*} lat 
     * @param {*} lng 
     * This code defines an asynchronous function called "reverseGeoCoding" that takes two parameters, "lat" and "lng", representing latitude and longitude coordinates, respectively. 
     * Within the function, a URL is constructed using the input parameters and an API key, with the variable name "api". 
     * The function then attempts to send a GET request to this API using the axios library. 
     * If the request is successful, the "display_name" field of the response data is assigned to the "streetAddress" property of the current object. 
     * If the request fails for any reason, the catch block does nothing.
     */
    async reverseGeoCoding(lat, lng) {
      var api = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`;
      try {
        var res = await axios.get(api);
        this.streetAddress = res.data.display_name;
      } catch (_) {
      }
    }
  },
  /**
   * @param {*} e 
   * This code defines a function called "preventEnterPropagation" that takes an event object "e" as its parameter. 
   * Inside the function, it checks if the event object is truthy (i.e. not null, undefined, false, 0, or ""), 
   * and if so, it calls the "preventDefault" method on the event object. 
   * This method prevents the default action associated with the event (e.g. submitting a form or clicking a link) from occurring. 
   * In this specific case, the function is likely used to prevent an unwanted submit or click event when the user presses the "Enter" key.
   */
  preventEnterPropagation: function (e) {
    if (e) e.preventDefault();
  },
  mounted() {
    this.initMap();
  }
};
</script>
