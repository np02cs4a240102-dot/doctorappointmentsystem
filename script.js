<script>
  const searchInput = document.getElementById("search");
  const specializationFilter = document.getElementById("specialization");
  const doctorCards = document.querySelectorAll(".doctor-card");

  function filterDoctors() {
    const searchValue = searchInput.value.toLowerCase();
    const specializationValue = specializationFilter.value.toLowerCase();

    doctorCards.forEach(card => {
      const name = card.dataset.name.toLowerCase();
      const specialization = card.dataset.specialization.toLowerCase();

      const matchesSearch = name.includes(searchValue);
      const matchesSpecialization = !specializationValue || specialization === specializationValue;

      if (matchesSearch && matchesSpecialization) {
        card.style.display = "block";
      } else {
        card.style.display = "none";
      }
    });
  }

  searchInput.addEventListener("input", filterDoctors);
  specializationFilter.addEventListener("change", filterDoctors);
</script>