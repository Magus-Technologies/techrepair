<script>
console.log('=== SCRIPT CARGADO ===');

const rucInp = document.getElementById('rucInp');
const btnBuscarRuc = document.getElementById('btnBuscarRuc');
const msgRuc = document.getElementById('msgRuc');

console.log('Elementos:', {rucInp, btnBuscarRuc, msgRuc});

if (btnBuscarRuc) {
  btnBuscarRuc.addEventListener('click', async () => {
    console.log('¡CLICK!');
    const doc = rucInp.value.trim();
    
    if (doc.length !== 11) {
      msgRuc.innerHTML = '<small style="color:#e05252">El RUC debe tener 11 dígitos.</small>';
      return;
    }
    
    btnBuscarRuc.disabled = true;
    btnBuscarRuc.textContent = 'Buscando...';
    
    try {
      const url = window.BASE_URL + 'modules/clientes/api_documento.php?doc=' + doc;
      console.log('URL:', url);
      const r = await fetch(url);
      const j = await r.json();
      console.log('Respuesta:', j);
      
      if (j.ok) {
        document.querySelector('input[name="razon_social"]').value = j.data.razon_social || '';
        document.querySelector('input[name="direccion"]').value = j.data.direccion || '';
        document.querySelector('input[name="distrito"]').value = j.data.distrito || '';
        document.querySelector('input[name="provincia"]').value = j.data.provincia || '';
        document.querySelector('input[name="departamento"]').value = j.data.departamento || '';
        msgRuc.innerHTML = '<small style="color:#10b981">✓ ' + j.data.razon_social + '</small>';
      } else {
        msgRuc.innerHTML = '<small style="color:#e05252">No encontrado</small>';
      }
    } catch (err) {
      console.error('ERROR:', err);
      msgRuc.innerHTML = '<small style="color:#e05252">Error: ' + err.message + '</small>';
    } finally {
      btnBuscarRuc.disabled = false;
      btnBuscarRuc.textContent = 'Buscar SUNAT';
    }
  });
}

if (rucInp) {
  rucInp.addEventListener('input', () => {
    rucInp.value = rucInp.value.replace(/\D/g, '');
  });
}

const colorInp = document.querySelector('input[name="color_primario"]');
const colorTxt = document.getElementById('colorTxt');
if (colorInp && colorTxt) {
  colorInp.addEventListener('input', () => colorTxt.value = colorInp.value);
}

// Preview de logo
const logoFile = document.getElementById('logoFile');
const logoPreview = document.getElementById('logoPreview');
if (logoFile && logoPreview) {
  logoFile.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (ev) => {
        logoPreview.innerHTML = '<img src="' + ev.target.result + '" style="max-width:100%;max-height:120px;margin-top:8px;border-radius:8px">';
      };
      reader.readAsDataURL(file);
    }
  });
}

// Nombre del archivo PEM
const pemFile = document.getElementById('pemFile');
const pemName = document.getElementById('pemName');
const frmPem = document.getElementById('frmPem');
if (pemFile && pemName && frmPem) {
  pemFile.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
      pemName.textContent = '📄 ' + file.name;
      if (confirm('¿Subir el certificado ' + file.name + ' ahora?')) {
        frmPem.submit();
      }
    }
  });
}

feather.replace();
console.log('=== SCRIPT COMPLETO ===');
</script>
