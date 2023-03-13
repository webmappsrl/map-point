<template>
  <div class="flex-container">
    <div class="flex-latitude">
        <label class="inline-block pt-2 leading-tight">
          Latitude
        </label>
        <input 
        @input="updateLatLng(lat,lng)" 
        v-model="lat" 
        type="text" 
        :disabled="!edit" 
        :class="{ 'form-input-black': edit }" 
        class="form-control form-input form-input-bordered shadow-lg">
    </div>
    <div class="flex-longitude">
        <label class="inline-block pt-2 leading-tight">
          Longitude
        </label>
        <input 
        @input="updateLatLng(lat,lng)" 
        v-model="lng" 
        type="text" 
        :disabled="!edit" 
        :class="{ 'form-input-black': edit }" 
        class="form-control form-input form-input-bordered shadow-lg">
    </div>
    <div class="flex-street">
        <label class="flex-street-label inline-block pt-2 leading-tight">
            Street Name
        </label>
        <input 
            type="text" 
            :disabled="!edit" 
            v-model="streetAddress"
            :class="{ 'form-input-black': edit }"
            @input="updateStreetAddress($event)" 
            class="flex-street-input form-control form-input form-input-bordered shadow-lg">
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
import axios from 'axios';
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
            mapDiv:null,
            streetAddress:null,
            api:'https://nominatim.openstreetmap.org/ui/search.html?q=',
            
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
        },
        async updateStreetAddress(event) {
            clearTimeout(this.debounce);
            this.debounce = setTimeout(async () => {
                console.log(event.target.value);
                var res = [{"place_id":113213715,"licence":"Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright","osm_type":"way","osm_id":24994966,"boundingbox":["37.5128627","37.5132466","15.0861492","15.0880696"],"lat":"37.5129407","lon":"15.0874726","display_name":"Via Redentore, Monserrato, Borgo-Sanzio, Catania, Sicilia, 95027, Italia","place_rank":26,"category":"highway","type":"residential","importance":0.30000999999999994,"geojson":{"type":"LineString","coordinates":[[15.0861492,37.5132466],[15.0864485,37.513127],[15.0874726,37.5129407],[15.087767,37.5128681],[15.0880696,37.5128627]]}},{"place_id":132937895,"licence":"Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright","osm_type":"way","osm_id":111471861,"boundingbox":["37.4940661","37.4951241","14.0601708","14.0602952"],"lat":"37.494952","lon":"14.0602952","display_name":"Via Redentore, Quartiere Provvidenza, Caltanissetta, Sicilia, 93100, Italia","place_rank":26,"category":"highway","type":"secondary","importance":0.30000999999999994,"geojson":{"type":"LineString","coordinates":[[14.0602608,37.4940661],[14.0602952,37.494952],[14.0602913,37.4949957],[14.0602783,37.4950365],[14.0602544,37.4950682],[14.0602346,37.4950884],[14.0602046,37.4951057],[14.0601708,37.4951241]]}},{"place_id":140864065,"licence":"Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright","osm_type":"way","osm_id":141654935,"boundingbox":["37.4942707","37.4955553","14.0601708","14.0617809"],"lat":"37.4947311","lon":"14.0607933","display_name":"Via Redentore, Quartiere Provvidenza, Caltanissetta, Sicilia, 93100, Italia","place_rank":26,"category":"highway","type":"tertiary","importance":0.30000999999999994,"geojson":{"type":"LineString","coordinates":[[14.0601708,37.4951241],[14.0602921,37.4953843],[14.06034,37.495462],[14.0603853,37.4955097],[14.0604416,37.4955379],[14.0605139,37.4955553],[14.0605823,37.4955493],[14.0606504,37.4955334],[14.0607089,37.4955068],[14.0607591,37.4954715],[14.0607905,37.4954198],[14.0608033,37.4953524],[14.0607933,37.4947311],[14.0607938,37.4946749],[14.0608118,37.4946168],[14.0608413,37.4945582],[14.0608855,37.4945185],[14.0609551,37.494482],[14.0610353,37.4944608],[14.061648,37.4943226],[14.0617196,37.4943007],[14.0617809,37.4942707]]}},{"place_id":141360352,"licence":"Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright","osm_type":"way","osm_id":141625605,"boundingbox":["37.4929428","37.4941707","14.0624888","14.0698381"],"lat":"37.4931768","lon":"14.0664444","display_name":"Via Redentore, Rione Angeli, Caltanissetta, Sicilia, 93100, Italia","place_rank":26,"category":"highway","type":"tertiary","importance":0.30000999999999994,"geojson":{"type":"LineString","coordinates":[[14.0624888,37.4934753],[14.0626718,37.4933993],[14.0628673,37.4933239],[14.0630285,37.493272],[14.0631733,37.493237],[14.0633748,37.493203],[14.0634674,37.4931877],[14.0635476,37.4931751],[14.0640392,37.4931197],[14.0641607,37.4931059],[14.0644351,37.4930747],[14.0646672,37.4930461],[14.0649041,37.4930169],[14.0652981,37.4929683],[14.0654324,37.4929533],[14.0655464,37.4929438],[14.0656397,37.4929428],[14.0658031,37.492952],[14.0660674,37.4929668],[14.0661618,37.4929729],[14.0662225,37.4929835],[14.0662714,37.4930044],[14.0663044,37.4930304],[14.0663856,37.4931121],[14.0664444,37.4931768],[14.0665002,37.4932291],[14.0665763,37.4932786],[14.0666407,37.493314],[14.0669649,37.4934658],[14.06718,37.4935665],[14.0672926,37.4936159],[14.0674321,37.4936696],[14.0675256,37.4937029],[14.0676331,37.4937288],[14.0677397,37.4937472],[14.0686118,37.4938497],[14.0691566,37.4939237],[14.0692122,37.4939312],[14.0693316,37.4939557],[14.0694388,37.4939898],[14.0695582,37.4940589],[14.0696254,37.4940998],[14.0697072,37.494133],[14.0698381,37.4941707]]}},{"place_id":121216335,"licence":"Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright","osm_type":"way","osm_id":51236117,"boundingbox":["46.0406372","46.0408321","12.9309163","12.9311056"],"lat":"46.0408321","lon":"12.9309163","display_name":"Via Redentore, Redenzicco, Sedegliano, Udine, Friuli-Venezia Giulia, 33039, Italia","place_rank":26,"category":"highway","type":"unclassified","importance":0.30000999999999994,"geojson":{"type":"LineString","coordinates":[[12.9311056,46.0406372],[12.9309163,46.0408321]]}},{"place_id":121825483,"licence":"Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright","osm_type":"way","osm_id":51236115,"boundingbox":["46.0401989","46.0403158","12.9313817","12.9315523"],"lat":"46.0402585","lon":"12.931466968716931","display_name":"Via Redentore, Redenzicco, Sedegliano, Udine, Friuli-Venezia Giulia, Italia","place_rank":26,"category":"highway","type":"unclassified","importance":0.30000999999999994,"geojson":{"type":"Polygon","coordinates":[[[12.9313817,46.0402563],[12.9313891,46.0402334],[12.9314223,46.040207],[12.9314463,46.0402],[12.9314793,46.0401989],[12.9315147,46.0402083],[12.9315387,46.0402253],[12.9315494,46.0402418],[12.9315523,46.0402607],[12.9315495,46.0402728],[12.9315384,46.0402901],[12.9315205,46.0403037],[12.9314828,46.0403158],[12.9314408,46.0403139],[12.9314084,46.0403007],[12.9313893,46.0402821],[12.9313833,46.0402691],[12.9313817,46.0402563]]]}},{"place_id":125141152,"licence":"Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright","osm_type":"way","osm_id":73031669,"boundingbox":["46.3167018","46.3169376","13.0513859","13.0534118"],"lat":"46.3167018","lon":"13.0523761","display_name":"Via Redentore, Cjalcôr, Alesso, Trasaghis, Udine, Friuli-Venezia Giulia, Italia","place_rank":26,"category":"highway","type":"residential","importance":0.30000999999999994,"geojson":{"type":"LineString","coordinates":[[13.0534118,46.3169376],[13.0532954,46.3169227],[13.0528789,46.3167771],[13.0527797,46.3167426],[13.0527073,46.3167256],[13.0525428,46.316707],[13.0523761,46.3167018],[13.0522697,46.3167066],[13.0518074,46.3167444],[13.0513859,46.316785]]}},{"place_id":119851645,"licence":"Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright","osm_type":"way","osm_id":42772572,"boundingbox":["45.7699889","45.7710149","12.5976672","12.5981937"],"lat":"45.7704771","lon":"12.5977962","display_name":"Via Redentore, Ponte Redigole, Motta di Livenza, Treviso, Veneto, 31045, Italia","place_rank":26,"category":"highway","type":"residential","importance":0.30000999999999994,"geojson":{"type":"LineString","coordinates":[[12.5981937,45.7710149],[12.5980706,45.7708282],[12.5978643,45.770665],[12.5977962,45.7704771],[12.5976672,45.7699889]]}},{"place_id":182602302,"licence":"Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright","osm_type":"way","osm_id":309475425,"boundingbox":["41.24251","41.2461264","14.4857428","14.4904664"],"lat":"41.2443389","lon":"14.488076","display_name":"Via Redentore, Cese San Manno, San Salvatore Telesino, Benevento, Campania, 82039, Italia","place_rank":26,"category":"highway","type":"residential","importance":0.30000999999999994,"geojson":{"type":"LineString","coordinates":[[14.4857428,41.2461184],[14.4857919,41.2461264],[14.4858332,41.2461162],[14.4859869,41.246016],[14.4861919,41.2458757],[14.4863931,41.245738],[14.486736,41.2455367],[14.4870924,41.2453503],[14.4873372,41.2452223],[14.4874311,41.2451535],[14.487518,41.2450546],[14.4878485,41.2445846],[14.487929,41.2444701],[14.4880487,41.244358],[14.488076,41.2443389],[14.4885032,41.2440406],[14.488657,41.2439333],[14.4888958,41.2437405],[14.4889905,41.243664],[14.4891055,41.2435923],[14.4892442,41.2435058],[14.4895541,41.2433126],[14.4895845,41.243288],[14.4897255,41.243174],[14.4899299,41.2429769],[14.4900989,41.2428138],[14.4904664,41.24251]]}},{"place_id":175274917,"licence":"Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright","osm_type":"way","osm_id":271074596,"boundingbox":["45.4580689","45.4585036","12.0037449","12.0040367"],"lat":"45.4581157","lon":"12.0037949","display_name":"Via Redentore, Rivale, Pianiga, Venezia, Veneto, 30039, Italia","place_rank":26,"category":"highway","type":"residential","importance":0.30000999999999994,"geojson":{"type":"LineString","coordinates":[[12.0038737,45.4585036],[12.0038324,45.4584199],[12.0037449,45.458142],[12.0037949,45.4581157],[12.0040367,45.4580689]]}}];
                try{
                    const lat = res[0].lat;
                    const lng = res[0].lon;
                    this.updateLatLng(lat,lng);
                }catch(error){
                    console.log(error);
                }
            }, 600)
        },
        async reverseGeoCoding(lat, lng){
            var api = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`;
            var res  = await axios.get(api);
        }    
    },
    mounted() {
        this.initMap();
    },
};
</script>
