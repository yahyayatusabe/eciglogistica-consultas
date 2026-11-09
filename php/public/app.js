// Frontend sin dependencias: habla con la API PHP mediante fetch.
// Seguridad: todo el contenido del usuario se pinta con textContent (nunca innerHTML) -> sin XSS.

const API = 'api';
const CATEGORIAS = {
  'Soporte Técnico': 'soporte',
  'Duda Comercial': 'comercial',
  'Agradecimiento': 'gracias',
};

const EJEMPLOS = [
  { nombre_tienda: 'Vapers Gandia', email: 'tienda@vapersgandia.es',
    texto: 'Buenos días, en el pedido del martes venían 3 kits pod que no encienden aunque estén cargados. ¿Cómo tramitamos la garantía?' },
  { nombre_tienda: 'Estanco Mislata 3', email: 'estancomislata3@gmail.com',
    texto: 'Hola, queremos empezar a vender vapeo en el estanco. ¿Qué pedido mínimo tenéis y hay descuento por volumen en desechables?' },
  { nombre_tienda: 'Cloud Shop Sagunto', email: 'hola@cloudshopsagunto.com',
    texto: 'Solo quería deciros que el pedido llegó en menos de 24 horas y todo perfecto. ¡Gracias por el trato!' },
];

const $ = (sel) => document.querySelector(sel);
const form = $('#form-consulta');
const lista = $('#lista');
const estadoCarga = $('#estado-carga');
const btnEnviar = $('#btn-enviar');

let filtroActual = null; // null = todas

// ---------------- API ----------------

async function pedir(url, opciones = {}) {
  const resp = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    ...opciones,
  });
  const datos = await resp.json().catch(() => ({}));
  if (!resp.ok) {
    const error = new Error(datos.error || `Error HTTP ${resp.status}`);
    error.campos = datos.campos;
    throw error;
  }
  return datos;
}

// ---------------- Listado ----------------

async function cargarConsultas(idResaltado = null) {
  let params = '';
  if (filtroActual === '__error') params = '?estado=error';
  else if (filtroActual) params = `?categoria=${encodeURIComponent(filtroActual)}`;
  try {
    const { consultas, resumen } = await pedir(`${API}/consultas.php${params}`);
    cargarModo();
    pintarFiltros(resumen);
    pintarLista(consultas, idResaltado);
  } catch (e) {
    estadoCarga.textContent = 'No se pudieron cargar las consultas: ' + e.message;
  }
}

function pintarFiltros(resumen) {
  const contenedor = $('#filtros');
  contenedor.replaceChildren();

  const opciones = [[null, 'Todas', resumen.total], ...Object.entries(resumen.por_categoria).map(([c, n]) => [c, c, n])];
  if (resumen.por_estado.error > 0) {
    opciones.push(['__error', 'Con error', resumen.por_estado.error]);
  }

  for (const [valor, etiqueta, n] of opciones) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'filtro';
    b.setAttribute('role', 'tab');
    b.setAttribute('aria-selected', String(filtroActual === valor));
    b.textContent = etiqueta;
    const num = document.createElement('span');
    num.className = 'n';
    num.textContent = n;
    b.append(num);
    b.addEventListener('click', () => { filtroActual = valor; cargarConsultas(); });
    contenedor.append(b);
  }
}

function pintarLista(consultas, idResaltado) {
  lista.replaceChildren(...consultas.map((c) => crearItem(c, c.id === idResaltado)));
  estadoCarga.textContent = consultas.length ? '' : 'No hay consultas en esta vista.';
}

function crearItem(c, resaltar) {
  const li = $('#tpl-consulta').content.firstElementChild.cloneNode(true);
  if (resaltar) li.classList.add('nueva');

  const badge = li.querySelector('.badge');
  if (c.estado === 'clasificada') {
    badge.textContent = c.categoria;
    badge.classList.add(CATEGORIAS[c.categoria]);
  } else {
    badge.textContent = c.estado === 'error' ? 'Error al clasificar' : 'Pendiente';
    badge.classList.add(c.estado);
  }

  li.querySelector('.tienda').textContent = c.nombre_tienda;
  li.querySelector('.email').textContent = c.email;
  li.querySelector('.texto').textContent = c.texto;

  const fecha = li.querySelector('.fecha');
  fecha.dateTime = c.created_at;
  fecha.textContent = formatearFecha(c.created_at);

  const meta = li.querySelector('.meta');
  if (c.estado === 'clasificada') {
    meta.textContent = `Clasificado por: ${c.clasificado_por}`;
  } else if (c.estado === 'error') {
    meta.textContent = c.error_msg || 'Error desconocido';
    const btn = li.querySelector('.reintentar');
    btn.hidden = false;
    btn.addEventListener('click', () => reintentar(c.id, btn));
  }
  return li;
}

function formatearFecha(valor) {
  const d = new Date(valor.replace(' ', 'T'));
  return isNaN(d) ? valor : d.toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' });
}

async function reintentar(id, btn) {
  btn.disabled = true;
  btn.textContent = 'Clasificando…';
  try {
    await pedir(`${API}/reintentar.php`, { method: 'POST', body: JSON.stringify({ id }) });
  } catch (e) {
    alert('No se pudo reintentar: ' + e.message);
  }
  cargarConsultas(id);
}

// ---------------- Formulario ----------------

form.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const datos = Object.fromEntries(new FormData(form));
  const errores = $('#errores');
  errores.textContent = '';

  btnEnviar.disabled = true;
  btnEnviar.textContent = 'Clasificando con IA…';
  try {
    const consulta = await pedir(`${API}/consultas.php`, { method: 'POST', body: JSON.stringify(datos) });
    form.reset();
    actualizarContador();
    filtroActual = null; // mostrar todas para que se vea la nueva
    await cargarConsultas(consulta.id);
  } catch (e) {
    errores.textContent = e.campos ? Object.values(e.campos).join('. ') : e.message;
  } finally {
    btnEnviar.disabled = false;
    btnEnviar.textContent = 'Enviar y clasificar';
  }
});

const textarea = form.elements.texto;
const actualizarContador = () => { $('#contador').textContent = textarea.value.length; };
textarea.addEventListener('input', actualizarContador);

document.querySelectorAll('[data-ejemplo]').forEach((btn) => {
  btn.addEventListener('click', () => {
    const ej = EJEMPLOS[btn.dataset.ejemplo];
    for (const [campo, valor] of Object.entries(ej)) form.elements[campo].value = valor;
    actualizarContador();
  });
});

$('#btn-recargar').addEventListener('click', () => cargarConsultas());

// Indicador de modo (IA real o simulado) en la cabecera
async function cargarModo() {
  const el = $('#modo-ia');
  try {
    const { modo, modelo } = await pedir(`${API}/estado.php`);
    el.textContent = modo === 'ia' ? `IA activa · ${modelo}` : 'Modo simulado (sin API key)';
  } catch {
    el.textContent = 'Servicio de IA no disponible';
  }
  el.hidden = false;
}

cargarConsultas();
