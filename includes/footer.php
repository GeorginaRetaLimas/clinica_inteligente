</div> <!-- End container -->

<footer class="bg-white mt-auto py-3 border-top">
    <div class="container text-center text-muted">
        <small>&copy; <?php echo date('Y'); ?> Aplicación Web de Manejo de Datos Clínicos - Asistente IA</small>
    </div>
</footer>

<!-- Componentes Modales Globales de AURA (Estilo Pastel Redondeado) -->
<style>
.aura-modal-content {
    background-color: #fdfaf5;
    border-radius: 12px;
    color: #424f8d;
    text-align: center;
    padding: 2.5rem 1.5rem 2rem 1.5rem;
    border: none;
    font-family: inherit;
}
.aura-modal-title {
    font-weight: 700;
    font-size: 1.3rem;
    margin-bottom: 0.8rem;
    color: #424f8d;
}
.aura-modal-body {
    font-size: 0.95rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
    color: #424f8d;
}
.aura-modal-btn {
    background-color: #4459ac;
    color: #fff;
    border-radius: 8px;
    padding: 0.5rem 2rem;
    font-weight: 600;
    border: none;
    transition: background-color 0.2s;
    font-size: 1rem;
}
.aura-modal-btn:hover {
    background-color: #37488e;
    color: #fff;
}
.aura-modal-btn-cancel {
    background-color: #fff;
    color: #424f8d;
    border: 1px solid #dcdce6;
}
.aura-modal-btn-cancel:hover {
    background-color: #f4f4f8;
    color: #424f8d;
}
.aura-icon-container {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 4px solid #f67a7a;
    color: #f67a7a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3.5rem;
    margin: 0 auto 1.5rem auto;
    background-color: transparent;
}
.aura-icon-container.success { border-color: #72d5a3; color: #72d5a3; }
.aura-icon-container.info { border-color: #64aec0; color: #64aec0; }
.aura-icon-container.warning { border-color: #e3b04a; color: #e3b04a; }
</style>

<div class="modal fade" id="auraGlobalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm border-0">
    <div class="modal-content aura-modal-content shadow-lg">
      <div id="auraGlobalModalIcon" class="aura-icon-container success">
        <i class="bi bi-check-lg" id="auraGlobalModalIconBi"></i>
      </div>
      <h4 class="aura-modal-title" id="auraGlobalModalTitle">Mensaje</h4>
      <div class="aura-modal-body" id="auraGlobalModalBody">Contenido</div>
      <div>
        <button type="button" class="btn aura-modal-btn" data-bs-dismiss="modal">Aceptar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="auraConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm border-0">
    <div class="modal-content aura-modal-content shadow-lg">
      <div class="aura-icon-container warning">
        <i class="bi bi-exclamation-triangle"></i>
      </div>
      <h4 class="aura-modal-title" id="auraConfirmModalTitle">Confirmar Acción</h4>
      <div class="aura-modal-body" id="auraConfirmModalBody">¿Estás seguro de continuar?</div>
      <div class="d-flex justify-content-center gap-3">
        <button type="button" class="btn aura-modal-btn aura-modal-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn aura-modal-btn" id="auraConfirmModalBtn" style="background-color: #f57373;">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Scripts personalizados de la aplicacion -->
<script src="/clinica_app/public/assets/js/scripts.js"></script>
</body>
</html>
