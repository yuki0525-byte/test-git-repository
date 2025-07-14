<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>ホテル表示アプリ（飲食店から徒歩20分圏内）</title>
    <style>
        #map {
            height: 600px;
            width: 100%;
        }

        #controls {
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <h2>観光地を検索、またはマップをクリックして、周辺の飲食店から徒歩20分以内のホテルを表示</h2>
    <div id="controls">
        <input type="text" id="keyword" placeholder="例：京都タワー、浅草寺など" size="40">
        <button onclick="handleKeywordSearch()">検索</button>
    </div>
    <div id="map"></div>

    <script>
        let map;
        let centerMarker = null;
        let hotelMarkers = [];

        function clearMarkers(markers) {
            markers.forEach(m => m.setMap(null));
            markers.length = 0;
        }

        function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                center: {
                    lat: 35.681236,
                    lng: 139.767125
                },
                zoom: 13,
            });

            map.addListener("click", (e) => {
                const location = e.latLng;
                handleLocationSearch(location);
            });
        }

        function handleLocationSearch(location) {
            if (centerMarker) centerMarker.setMap(null);
            clearMarkers(hotelMarkers);

            map.setCenter(location);
            centerMarker = new google.maps.Marker({
                map: map,
                position: location,
                title: "検索拠点",
                icon: "http://maps.google.com/mapfiles/ms/icons/red-dot.png"
            });

            searchFilteredHotels(location);
        }

        function handleKeywordSearch() {
            const keyword = document.getElementById("keyword").value;
            if (!keyword) return alert("キーワードを入力してください。");

            const service = new google.maps.places.PlacesService(map);
            const request = {
                query: keyword,
                fields: ['name', 'geometry']
            };

            service.findPlaceFromQuery(request, (results, status) => {
                if (status === google.maps.places.PlacesServiceStatus.OK && results[0]) {
                    const location = results[0].geometry.location;
                    handleLocationSearch(location);
                } else {
                    alert("場所が見つかりませんでした。");
                }
            });
        }

        function searchFilteredHotels(center) {
            const placesService = new google.maps.places.PlacesService(map);
            const directionsService = new google.maps.DirectionsService();
            const infoWindow = new google.maps.InfoWindow();

            placesService.nearbySearch({
                location: center,
                radius: 20000,
                type: "restaurant"
            }, (restaurants, status) => {
                if (status !== google.maps.places.PlacesServiceStatus.OK || restaurants.length === 0) {
                    alert("飲食店が見つかりませんでした。");
                    return;
                }

                placesService.nearbySearch({
                    location: center,
                    radius: 20000,
                    keyword: "ホテル"
                }, (hotels, hotelStatus) => {
                    if (hotelStatus !== google.maps.places.PlacesServiceStatus.OK || hotels.length === 0) {
                        alert("ホテルが見つかりませんでした。");
                        return;
                    }

                    let matchedHotelCount = 0;
                    let totalChecks = 0;
                    const restaurantLimit = 5;
                    const hotelLimit = 20;
                    const totalExpectedChecks = Math.min(restaurantLimit, restaurants.length) * Math.min(hotelLimit, hotels.length);

                    restaurants.slice(0, restaurantLimit).forEach(restaurant => {
                        hotels.slice(0, hotelLimit).forEach(hotel => {
                            directionsService.route({
                                origin: restaurant.geometry.location,
                                destination: hotel.geometry.location,
                                travelMode: google.maps.TravelMode.WALKING
                            }, (response, status) => {
                                totalChecks++;

                                if (status === "OK") {
                                    const durationSec = response.routes[0].legs[0].duration.value;
                                    if (durationSec <= 1200) {
                                        matchedHotelCount++;
                                        const hotelLoc = hotel.geometry.location;
                                        const marker = new google.maps.Marker({
                                            map: map,
                                            position: hotelLoc,
                                            title: hotel.name,
                                            icon: "http://maps.google.com/mapfiles/ms/icons/green-dot.png"
                                        });
                                        hotelMarkers.push(marker);

                                        const searchUrl = "https://www.google.com/search?q=" + encodeURIComponent(hotel.name + " 予約");
                                        const content = `
                                            <div style="font-size:14px; max-width:250px;">
                                                <strong>🏨 ${hotel.name}</strong><br>
                                                評価: ${hotel.rating || '不明'}（${hotel.user_ratings_total || 0}件）<br>
                                                住所: ${hotel.vicinity || '不明'}<br>
                                                <a href="${searchUrl}" target="_blank">🔗 予約サイトで検索</a>
                                            </div>
                                        `;
                                        marker.addListener("click", () => {
                                            infoWindow.setContent(content);
                                            infoWindow.open(map, marker);
                                        });
                                    }
                                }

                                if (totalChecks === totalExpectedChecks && matchedHotelCount === 0) {
                                    alert("徒歩20分以内のホテルが見つかりませんでした。");
                                }
                            });
                        });
                    });
                });
            });
        }
    </script>

    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.maps.key') }}&libraries=places&callback=initMap" async defer></script>
</body>

</html>