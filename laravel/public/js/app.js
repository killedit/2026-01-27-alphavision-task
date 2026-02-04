let COLORS = typeof APP_COLORS !== 'undefined' ? APP_COLORS : [];

let map, restaurantMarkers = [], driverMarkers = [], driversLines = [];

function initMap(data) {
    if (!map) {
        map = L.map('map').setView([42.6977, 23.3219], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    }
    updateMap(data);
}

function updateMap(data) {

    [...restaurantMarkers, ...driverMarkers, ...driversLines].forEach(l => map.removeLayer(l));
    restaurantMarkers = []; driverMarkers = []; driversLines = [];

    data.restaurants_after.forEach((res, i) => {
        const color = COLORS[i % COLORS.length] || '#808080';
        const icon = L.divIcon({
            html: `<div style="background:${color}; width:28px; height:28px; color:white; text-align:center; line-height:28px; border:2px solid #000; border-radius:4px; font-weight:bold; font-size:14px;">${i+1}</div>`,
            className: 'res-marker',
            iconSize: [28, 28]
        });
        const m = L.marker([res.lat, res.lng], {icon, zIndexOffset: 1000}).addTo(map);
        m.bindPopup(`<b>${res.title}</b><br>Orders Remaining: ${res.orders_count}`);
        restaurantMarkers.push(m);
        res.color = color;
    });

    data.drivers.forEach(d => {
        const res = data.restaurants_after.find(r => r.id === d.assigned_restaurant_id);
        const color = res ? res.color : '#808080';

        // Thicker Lines (Weight: 3)
        if (res) {
            const line = L.polyline([[res.lat, res.lng], [d.position.lat, d.position.lng]], {
                color: color,
                weight: 3,
                opacity: 0.6,
                dashArray: res ? null : '5, 10'
            }).addTo(map);
            driversLines.push(line);
        }

        const dMark = L.circleMarker([d.position.lat, d.position.lng], {
            radius: 8,
            fillColor: color,
            color: '#000',
            weight: 1.5,
            fillOpacity: 0.9
        }).addTo(map);
        driverMarkers.push(dMark);

        const label = L.divIcon({
            html: `<div style="color:white; text-align:center; font-size:10px; font-weight:bold; width:16px; line-height:16px;">${d.id}</div>`,
            className: 'driver-label',
            iconSize: [16, 16],
            iconAnchor: [8, 8] // Centers the number inside the circle
        });
        const textMarker = L.marker([d.position.lat, d.position.lng], {icon: label, zIndexOffset: 500}).addTo(map);
        driverMarkers.push(textMarker);
    });
}

$(document).on('click', '.sortable', function() {
    const table = $(this).closest('table');
    const index = $(this).index();
    const type = $(this).data('type');
    const rows = table.find('tbody tr').get();
    const isAsc = $(this).hasClass('asc');

    rows.sort((a, b) => {
        let valA = $(a).children('td').eq(index).text().trim();
        let valB = $(b).children('td').eq(index).text().trim();
        if (type === 'num') {
            return isAsc ? parseFloat(valB) - parseFloat(valA) : parseFloat(valA) - parseFloat(valB);
        } else {
            return isAsc ? valB.localeCompare(valA) : valA.localeCompare(valB);
        }
    });

    table.find('.sortable').removeClass('asc desc');
    $(this).addClass(isAsc ? 'desc' : 'asc');
    table.find('tbody').append(rows);
});

$(document).ready(function() {
    if (window.initialReport) {
        initMap(window.initialReport);
    }

    $('#simulateBtn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Simulating...');

        $.ajax({
            url: '/restaurant/simulate',
            method: 'GET',
            dataType: 'json',
            success: function(res) {

// console.log(res.stats);

                $('#totalDrivers').text(res.stats.total_drivers_assigned);
                $('#avgDistance').text(res.stats.average_distance.toFixed(2));
                $('#totalOrdersAssigned').text(res.stats.total_orders_assigned);
                $('#utilizationRate').text(res.stats.utilization_rate.toFixed(1));

                let resHtml = '';
                res.restaurants_before.forEach((rb, i) => {
                    const ra = res.restaurants_after[i];
                    const color = COLORS[i % COLORS.length] || '#808080';
                    const dIds = res.drivers.filter(d => d.assigned_restaurant_id === rb.id).map(d => d.id).join(', ');
                    resHtml += `<tr style="background-color: ${color}; color: white;">
                        <td>${rb.id}</td><td>${rb.title}</td><td>${rb.orders_count}</td><td>${ra.orders_count}</td><td>${dIds || '—'}</td></tr>`;
                });
                $('#restaurantsComparison').html(resHtml);

                let dHtml = '';
                res.drivers.forEach(d => {
                    dHtml += `
                    <tr>
                        <td>${d.id}</td>
                        <td>${d.orders_assigned}</td>
                        <td>${d.assigned_restaurant_id}</td>
                        <td>${d.assigned_restaurant_title}</td>
                        <td>${parseFloat(d.distance_to_assigned).toFixed(2)}</td>
                        <td><span class="badge ${d.assigned_restaurant_id > 0 ? 'bg-success' : 'bg-secondary'}">${d.assigned_restaurant_id > 0 ? 'Assigned' : 'Idle'}</span></td>
                    /tr>`;
                });
                $('#driversTable').html(dHtml);

                updateMap(res);
            },
            error: function(xhr) {
                console.error(xhr.responseText);
            },
            complete: function() {
                btn.prop('disabled', false).text('Run New Simulation');
            }
        });
    });
});
