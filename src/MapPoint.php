<?php

namespace Wm\MapPoint;

use Laravel\Nova\Fields\Field;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;

class MapPoint extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'map-point';
    public $latlng = [];

    /**
     * Resolve the field's value.
     */
    public function resolve($resource, ?string $attribute = null): void
    {
        parent::resolve($resource, $attribute = null);
        $this->latlng = $this->geometryToLatLon($this->value);
        $this->withMeta(['latlng' => $this->latlng]);
    }

    public function fillModelWithData(mixed $model, mixed $value, string $attribute)
    {
        $lonLat = explode(',', $value);
        $value = $this->latLonToGeometry($lonLat);

        $oldValue = $this->geometryToLatLon($model->{$attribute});
        if ($value != $oldValue) {
            parent::fillModelWithData($model, $value, $attribute);
        }
    }

    public function geometryToLatLon($geometry)
    {
        $coords = [];
        if (!is_null($geometry)) {
            // g->coordinates == [lon,lat] we needs inverted order
            $g = json_decode(DB::select("SELECT st_asgeojson('$geometry') as g")[0]->g);
            $coords = [$g->coordinates[1], $g->coordinates[0]];
        }
        return $coords;
    }

    public function latLonToGeometry($latlon)
    {
        $lat = $latlon[0];
        $lon = $latlon[1];
        if ($lat != null && $lon != null) {
            return DB::select("SELECT ST_GeomFromText('POINT($lon $lat)') as g")[0]->g;
        } else {
            return null;
        }
    }
}
