// Funciones utilitarias
function mostrarAlerta(mensaje, tipo = "success") {
  const alertaExistente = document.querySelector(".alert")
  if (alertaExistente) {
    alertaExistente.remove()
  }

  const alerta = document.createElement("div")
  alerta.className = `alert alert-${tipo}`
  alerta.textContent = mensaje

  const container = document.querySelector(".card-content")
  container.insertBefore(alerta, container.firstChild)

  setTimeout(() => {
    alerta.remove()
  }, 5000)
}

function setLoading(elemento, loading) {
  if (loading) {
    elemento.classList.add("loading")
    elemento.disabled = true
  } else {
    elemento.classList.remove("loading")
    elemento.disabled = false
  }
}

// Funciones para formulario de monitoreo industrial
function confirmarPresion() {
  const boton = document.getElementById("btnConfirmarPresion")
  setLoading(boton, true)

  const datos = {
    accion: "agregar_presion",
    presion_tanque: document.getElementById("presionTanque").value,
    presion_planta: document.getElementById("presionPlanta").value,
    presion_falcon: document.getElementById("presionFalcon").value,
    nivel_cisterna: document.getElementById("nivelCisterna").value,
  }

  fetch("operador.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(datos),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        mostrarAlerta(data.message, "success")
        cargarRegistrosPresion()
      } else {
        mostrarAlerta(data.message, "error")
      }
    })
    .catch((error) => {
      mostrarAlerta("Error de conexión", "error")
    })
    .finally(() => {
      setLoading(boton, false)
    })
}

function registrarLavado() {
  const boton = document.getElementById("btnLavado")
  setLoading(boton, true)

  const filtrosSeleccionados = []

  // Verificar filtros línea norte
  if (document.getElementById("norteF1").checked) filtrosSeleccionados.push("LINEA NORTE - FILTRO 1")
  if (document.getElementById("norteF2").checked) filtrosSeleccionados.push("LINEA NORTE - FILTRO 2")
  if (document.getElementById("norteF3").checked) filtrosSeleccionados.push("LINEA NORTE - FILTRO 3")

  // Verificar filtros línea sur
  if (document.getElementById("surF1").checked) filtrosSeleccionados.push("LINEA SUR - FILTRO 1")
  if (document.getElementById("surF2").checked) filtrosSeleccionados.push("LINEA SUR - FILTRO 2")
  if (document.getElementById("surF3").checked) filtrosSeleccionados.push("LINEA SUR - FILTRO 3")

  if (filtrosSeleccionados.length === 0) {
    mostrarAlerta("Debe seleccionar al menos un filtro", "error")
    setLoading(boton, false)
    return
  }

  const horaInicio = document.getElementById("horaInicio").value
  const horaFinal = document.getElementById("horaFinal").value

  if (!horaInicio || !horaFinal) {
    mostrarAlerta("Debe completar hora de inicio y hora final", "error")
    setLoading(boton, false)
    return
  }

  const datos = {
    accion: "registrar_lavado",
    filtros: filtrosSeleccionados,
    hora_inicio: horaInicio,
    hora_final: horaFinal,
  }

  fetch("operador.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(datos),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        mostrarAlerta(data.message, "success")
        // Limpiar checkboxes
        document.querySelectorAll(".checkbox").forEach((cb) => (cb.checked = false))
        cargarNovedades()
      } else {
        mostrarAlerta(data.message, "error")
      }
    })
    .catch((error) => {
      mostrarAlerta("Error de conexión", "error")
    })
    .finally(() => {
      setLoading(boton, false)
    })
}

function setearNivel(tipoQuimico) {
  const nivel = document.getElementById(`nivel${tipoQuimico.charAt(0).toUpperCase() + tipoQuimico.slice(1)}`).value

  if (!nivel) {
    mostrarAlerta("Debe ingresar un nivel", "error")
    return
  }

  const datos = {
    accion: "setear_nivel",
    tipo_quimico: tipoQuimico,
    nivel: nivel,
  }

  fetch("operador.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(datos),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        mostrarAlerta(data.message, "success")
      } else {
        mostrarAlerta(data.message, "error")
      }
    })
    .catch((error) => {
      mostrarAlerta("Error de conexión", "error")
    })
}

function agregarNovedad() {
  const descripcion = document.getElementById("nuevaNovedad").value.trim()

  if (!descripcion) {
    mostrarAlerta("Debe escribir una novedad", "error")
    return
  }

  const datos = {
    accion: "agregar_novedad",
    descripcion: descripcion,
  }

  fetch("operador.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(datos),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        mostrarAlerta(data.message, "success")
        document.getElementById("nuevaNovedad").value = ""
        cargarNovedades()
      } else {
        mostrarAlerta(data.message, "error")
      }
    })
    .catch((error) => {
      mostrarAlerta("Error de conexión", "error")
    })
}

function eliminarNovedad(id) {
  if (!confirm("¿Está seguro de eliminar esta novedad?")) {
    return
  }

  const datos = {
    accion: "eliminar_novedad",
    id: id,
  }

  fetch("operador.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(datos),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        mostrarAlerta(data.message, "success")
        cargarNovedades()
      } else {
        mostrarAlerta(data.message, "error")
      }
    })
    .catch((error) => {
      mostrarAlerta("Error de conexión", "error")
    })
}

function cerrarTurno() {
  if (!confirm("¿Está seguro de cerrar el turno? Esta acción no se puede deshacer.")) {
    return
  }

  const boton = document.getElementById("btnCerrarTurno")
  setLoading(boton, true)

  const datos = {
    accion: "cerrar_turno",
  }

  fetch("operador.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(datos),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        mostrarAlerta(data.message, "success")
        setTimeout(() => {
          location.reload()
        }, 2000)
      } else {
        mostrarAlerta(data.message, "error")
      }
    })
    .catch((error) => {
      mostrarAlerta("Error de conexión", "error")
    })
    .finally(() => {
      setLoading(boton, false)
    })
}

// Funciones para cargar datos
function cargarRegistrosPresion() {
  fetch("operador.php?accion=obtener_registros_presion")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const tbody = document.getElementById("tablaRegistrosPresion")
        tbody.innerHTML = ""

        data.data.forEach((registro) => {
          const fila = document.createElement("tr")
          fila.innerHTML = `
                    <td>${registro.presion_tanque}</td>
                    <td>${registro.presion_planta}</td>
                    <td>${registro.presion_falcon}</td>
                    <td>${registro.nivel_cisterna}</td>
                    <td>${new Date(registro.fecha_registro).toLocaleTimeString()}</td>
                `
          tbody.appendChild(fila)
        })

        // Mostrar tabla si hay registros
        const tablaContainer = document.getElementById("tablaRegistrosContainer")
        if (data.data.length > 0) {
          tablaContainer.style.display = "block"
        }
      }
    })
    .catch((error) => {
      console.error("Error cargando registros:", error)
    })
}

function cargarNovedades() {
  fetch("operador.php?accion=obtener_novedades")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const lista = document.getElementById("listaNovedades")
        lista.innerHTML = ""

        data.data.forEach((novedad) => {
          const item = document.createElement("li")
          item.className = "novedades-item"
          item.innerHTML = `
                    <div class="novedades-content">
                        <span class="bullet">•</span>
                        <span>${novedad.descripcion}</span>
                    </div>
                    <div class="novedades-actions">
                        <button class="icon-button" onclick="eliminarNovedad(${novedad.id})" title="Eliminar">
                            🗑️
                        </button>
                    </div>
                `
          lista.appendChild(item)
        })
      }
    })
    .catch((error) => {
      console.error("Error cargando novedades:", error)
    })
}

// Funciones para formulario de calidad de agua
function confirmarCalidadAgua() {
  const boton = document.getElementById("btnConfirmarCalidad")
  setLoading(boton, true)

  const datos = {
    accion: "agregar_calidad_agua",
    decantador_norte: {
      turbiedad: document.getElementById("decantadorNorteTurbiedad").value,
      ph: document.getElementById("decantadorNortePh").value,
    },
    decantador_sur: {
      turbiedad: document.getElementById("decantadorSurTurbiedad").value,
      ph: document.getElementById("decantadorSurPh").value,
    },
    cisterna: {
      turbiedad: document.getElementById("cisternaTurbiedad").value,
      ph: document.getElementById("cisternaPh").value,
      cloro_residual: document.getElementById("cisternaCloro").value,
    },
    rio: {
      turbiedad: document.getElementById("rioTurbiedad").value,
      ph: document.getElementById("rioPh").value,
    },
    filtro_norte: {
      filtro: document.getElementById("filtroNorte").value,
      turbiedad: document.getElementById("filtroNorteTurbiedad").value,
      ph: document.getElementById("filtroNortePh").value,
    },
    filtro_sur: {
      filtro: document.getElementById("filtroSur").value,
      turbiedad: document.getElementById("filtroSurTurbiedad").value,
      ph: document.getElementById("filtroSurPh").value,
    },
  }

  fetch("quimico.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(datos),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        mostrarAlerta(data.message, "success")
        cargarRegistrosCalidad()
        limpiarFormularioCalidad()
      } else {
        mostrarAlerta(data.message, "error")
      }
    })
    .catch((error) => {
      mostrarAlerta("Error de conexión", "error")
    })
    .finally(() => {
      setLoading(boton, false)
    })
}

function limpiarFormularioCalidad() {
  document.querySelectorAll(".input-small").forEach((input) => {
    if (input.type !== "select-one") {
      input.value = ""
    }
  })
  document.querySelectorAll(".select").forEach((select) => {
    select.selectedIndex = 0
  })
}

function cargarRegistrosCalidad() {
  fetch("quimico.php?accion=obtener_registros_calidad")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const tbody = document.getElementById("tablaRegistrosCalidad")
        tbody.innerHTML = ""

        data.data.forEach((registro) => {
          const fila = document.createElement("tr")
          fila.innerHTML = `
                    <td style="font-weight: 600;">${registro.lugar}</td>
                    <td>${registro.turbiedad || "-"}</td>
                    <td>${registro.ph || "-"}</td>
                    <td>${registro.cloro_residual || "-"}</td>
                    <td>${new Date(registro.fecha_registro).toLocaleTimeString()}</td>
                `
          tbody.appendChild(fila)
        })

        // Mostrar tabla si hay registros
        const tablaContainer = document.getElementById("tablaCalidadContainer")
        if (data.data.length > 0) {
          tablaContainer.style.display = "block"
        }
      }
    })
    .catch((error) => {
      console.error("Error cargando registros de calidad:", error)
    })
}

// Inicialización
document.addEventListener("DOMContentLoaded", () => {
  // Cargar datos iniciales si estamos en la página de monitoreo
  if (document.getElementById("tablaRegistrosPresion")) {
    cargarRegistrosPresion()
    cargarNovedades()
  }

  // Cargar datos iniciales si estamos en la página de calidad
  if (document.getElementById("tablaRegistrosCalidad")) {
    cargarRegistrosCalidad()
  }
})
