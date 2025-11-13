function filterByType(type) {
  const drones = document.querySelectorAll('.drone-card');
  drones.forEach(drone => {
    if (type === 'all' || drone.getAttribute('data-type') === type) {
      drone.style.display = 'block';
    } else {
      drone.style.display = 'none';
    }
  });
}

function filterDrones() {
  const searchTerm = document.getElementById('search').value.toLowerCase();
  const drones = document.querySelectorAll('.drone-card');
  drones.forEach(drone => {
    const model = drone.querySelector('h4').textContent.toLowerCase();
    if (model.includes(searchTerm)) {
      drone.style.display = 'block';
    } else {
      drone.style.display = 'none';
    }
  });
}


filterByType('all');
