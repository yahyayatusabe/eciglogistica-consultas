"""
Microservicio de clasificación con IA.

Una única responsabilidad: recibir un texto y devolver su análisis
(categoría, urgencia, resumen y borrador de respuesta).
No accede a la base de datos (eso lo hace el backend PHP).
"""

import logging
import os

from dotenv import load_dotenv
from flask import Flask, jsonify, request

# En local el .env está en la raíz del proyecto; en Docker las variables
# llegan por docker-compose y esta línea no hace nada.
load_dotenv(os.path.join(os.path.dirname(__file__), "..", ".env"))

from classifier import DEFAULT_MODEL, ClasificacionError, clasificar  # noqa: E402

MAX_CARACTERES = 2000

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
log = logging.getLogger("ai-service")

app = Flask(__name__)
app.json.ensure_ascii = False  # devolver "Técnico" y no "Técnico"


@app.get("/health")
def health():
    if os.getenv("AI_API_KEY", "").strip():
        return jsonify(status="ok", modo="ia", modelo=os.getenv("AI_MODEL", DEFAULT_MODEL))
    return jsonify(status="ok", modo="simulado", modelo=None)


@app.post("/clasificar")
def clasificar_endpoint():
    datos = request.get_json(silent=True) or {}
    texto = datos.get("texto")

    if not isinstance(texto, str) or not texto.strip():
        return jsonify(error="El campo 'texto' es obligatorio"), 400
    texto = texto.strip()
    if len(texto) > MAX_CARACTERES:
        return jsonify(error=f"El texto no puede superar {MAX_CARACTERES} caracteres"), 400

    nombre_tienda = datos.get("nombre_tienda")
    if not isinstance(nombre_tienda, str) or not nombre_tienda.strip():
        nombre_tienda = None  # opcional: solo se usa para personalizar el saludo
    else:
        nombre_tienda = nombre_tienda.strip()[:120]

    try:
        resultado = clasificar(texto, nombre_tienda)
    except ClasificacionError as e:
        log.warning("Fallo de clasificación: %s", e)
        return jsonify(error=str(e)), 502
    except Exception:
        log.exception("Error inesperado clasificando")
        return jsonify(error="Error interno del servicio de IA"), 500

    log.info("Clasificada como %s (%s)", resultado["categoria"], resultado["clasificado_por"])
    return jsonify(resultado), 200


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=int(os.getenv("PORT", 5000)))
