<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <script src="http://code.jquery.com/jquery-2.0.0.js"></script>
    <script src="http://d3js.org/d3.v3.min.js"></script>
    <script src="http://d3js.org/topojson.v1.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false"></script>
    <script src="guardian_parser.js"></script>
    <link rel="stylesheet" type="text/css" href="stylesheet.css"/>
    <style>
    #map {
      background-color: #fff;
      border: 5px solid #ccc;
        height: 500px;
    }
    .background {
      fill: none;
      pointer-events: all;
    }
    #countries, #states {
      fill: #cde;
      stroke: #fff;
      stroke-linejoin: round;
      stroke-linecap: round;
    }
    #countries .active, #states .active {
      fill: #89a;
    }
    #cities {
      stroke-width: 0;
    }
    .city {
      fill: #345;
      stroke: #fff;
    }
    pre.prettyprint {
      border: 1px solid #ccc;
      margin-bottom: 0;
      padding: 9.5px;
    }
    </style>
  </head>
  <body>
  
    <?php
  $date1 = getdate();
  $date = $date1[year] . "-" . $date1[mon] . "-" . $date1{mday};
  $APIcall = "http://content.guardianapis.com/search?api-key=dvwfajkrb36g8qfrbwqkk9xj&show-fields=body&from-date=" . $date . "&to-date=" . $date . "&section=world&order-by=relevance&format=json";
  $data = file_get_contents($APIcall);
  ?>
   
    <script>
    // In theory this will make a great big awesome map of awesomeness...
    var m_width = $("#map").width(),
        width = 938,
        height = 500,
        country,
        state;

    var projection = d3.geo.mercator()
        .scale(150)
        .translate([width / 2, height / 1.5]);

    var path = d3.geo.path()
        .projection(projection);

    var svg = d3.select("#map").append("svg")
        .attr("preserveAspectRatio", "xMidYMid")
        .attr("viewBox", "0 0 " + width + " " + height)
        .attr("width", m_width)
        .attr("height", m_width * height / width);

    svg.append("rect")
        .attr("class", "background")
        .attr("width", width)
        .attr("height", height)
        .on("click", country_clicked);

    var g = svg.append("g");

    d3.json("json/countries.topo.json", function(error, us) {
      g.append("g")
        .attr("id", "countries")
        .selectAll("path")
        .data(topojson.feature(us, us.objects.countries).features)
        .enter()
        .append("path")
        .attr("id", function(d) { return d.id; })
        .attr("d", path)
        .on("click", country_clicked);
    });

    function zoom(xyz) {
      g.transition()
        .duration(750)
        .attr("transform", "translate(" + projection.translate() + ")scale(" + xyz[2] + ")translate(-" + xyz[0] + ",-" + xyz[1] + ")")
        .selectAll(["#countries", "#states", "#cities"])
        .style("stroke-width", 1.0 / xyz[2] + "px")
        .selectAll(".city")
        .attr("d", path.pointRadius(20.0 / xyz[2]));
    }

    function get_xyz(d) {
      var bounds = path.bounds(d);
      var w_scale = (bounds[1][0] - bounds[0][0]) / width;
      var h_scale = (bounds[1][1] - bounds[0][1]) / height;
      var z = .96 / Math.max(w_scale, h_scale);
      var x = (bounds[1][0] + bounds[0][0]) / 2;
      var y = (bounds[1][1] + bounds[0][1]) / 2 + (height / z / 6);
      return [x, y, z];
    }

    function country_clicked(d) {
      g.selectAll(["#states", "#cities"]).remove();
      state = null;

      if (country) {
        g.selectAll("#" + country.id).style('display', null);
      }

      if (d && country !== d) {
        var xyz = get_xyz(d);
        country = d;

        if (d.id  == 'USA' || d.id == 'JPN') {
          d3.json("json/states_" + d.id.toLowerCase() + ".topo.json", function(error, us) {
            g.append("g")
              .attr("id", "states")
              .selectAll("path")
              .data(topojson.feature(us, us.objects.states).features)
              .enter()
              .append("path")
              .attr("id", function(d) { return d.id; })
              .attr("class", "active")
              .attr("d", path)
              .on("click", state_clicked);

            zoom(xyz);
            g.selectAll("#" + d.id).style('display', 'none');
          });      
        } else {
          zoom(xyz);
        }
      } else {
        var xyz = [width / 2, height / 1.5, 1];
        country = null;
        zoom(xyz);
      }
    }

    function state_clicked(d) {
      g.selectAll("#cities").remove();

      if (d && state !== d) {
        var xyz = get_xyz(d);
        state = d;

        country_code = state.id.substring(0, 3).toLowerCase();
        state_name = state.properties.name;

        d3.json("json/cities_" + country_code + ".topo.json", function(error, us) {
          g.append("g")
            .attr("id", "cities")
            .selectAll("path")
            .data(topojson.feature(us, us.objects.cities).features.filter(function(d) { return state_name == d.properties.state; }))
            .enter()
            .append("path")
            .attr("id", function(d) { return d.properties.name; })
            .attr("class", "city")
            .attr("d", path.pointRadius(20 / xyz[2]));

          zoom(xyz);
        });      
      } else {
        state = null;
        country_clicked(country);
      }
    }

    $(window).resize(function() {
      var w = $("#map").width();
      svg.attr("width", w);
      svg.attr("height", w * height / width);
    });
    </script>
       <div id="map"></div>
      <div id="stuff"></div>
      <script>
        geocoder = new google.maps.Geocoder();
        
        // Grabs the coords for the resultant country (city later on)
        function codeAddress(stuff) {
            geocoder.geocode( { address: stuff}, function(results, status) {
                if (status == google.maps.GeocoderStatus.OK) {
                    console.log(results[0].geometry.location.lng());
                    console.log(results[0].geometry.location.lat());
                } else {
                alert("Geocode was not successful for the following reason: " + status);
                }
            });
        }
        
        // Parses out the locale from a string, will get called once per article on the title
        function getCoords(ourString){
            ourArray=getLocale(ourString);
            return ourArray;
        }
    
    function grabStories() {
      var stories = new Array();
      var lat = 0;
      var lon = 0;
      var title = 0;
      var par = 0;
      var url = 0;
      var story = 0;
	  var coords = new Array();
      var file = "<?php echo $data; ?>";
      for (var prop in file) {
        title = prop.webTitle;
		coords = getCoords(title);
        lat = coords[0];
        lon = coords[1];
        url = prop.webUrl;
        story = {title: title, lat: lat, lon: lon, par: par, url: url};
        stories.push(story);
      }
      return stories;
    }
    
    console.log(grabStories());
        
      </script>
  </body>
</html>
