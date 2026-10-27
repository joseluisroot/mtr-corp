import DataTable from 'datatables.net-dt';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const table=document.querySelector('[data-inventory-table]');
if(table && table.tBodies[0].rows.length) new DataTable(table,{
  pageLength:25,order:[[0,'asc']],language:{search:'Buscar en la tabla:',lengthMenu:'Mostrar _MENU_',info:'_START_–_END_ de _TOTAL_ lotes',infoEmpty:'Sin lotes',infoFiltered:'(de _MAX_ en esta selección)',zeroRecords:'No se encontraron lotes',emptyTable:'Todavía no hay lotes',paginate:{first:'Primero',last:'Último',next:'Siguiente',previous:'Anterior'}}
});
document.querySelectorAll('[data-view]').forEach(button=>button.addEventListener('click',()=>{
 const isTable=button.dataset.view==='table';
 document.querySelector('[data-grid]').hidden=isTable;
 document.querySelector('[data-table-panel]').hidden=!isTable;
 document.querySelectorAll('[data-view]').forEach(b=>{b.setAttribute('aria-pressed',String(b===button));b.classList.toggle('bg-brand-50',b===button);});
}));
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',async event=>{
 if(form.dataset.confirmed==='yes') return;
 event.preventDefault();
 const result=await Swal.fire({title:'Confirmar cambios',text:form.dataset.confirm,icon:'question',showCancelButton:true,confirmButtonText:'Guardar cambios',cancelButtonText:'Volver',confirmButtonColor:'#1c653c'});
 if(result.isConfirmed){form.dataset.confirmed='yes';form.requestSubmit();}
}));
const menu=document.querySelector('[data-menu]');
menu?.addEventListener('click',()=>{const panel=document.querySelector('[data-sidebar]');panel.classList.toggle('hidden');menu.setAttribute('aria-expanded',String(!panel.classList.contains('hidden')));});

document.querySelectorAll('[data-commercial-form]').forEach(form=>{
 const kind=form.querySelector('[data-kind]'); const intent=form.querySelector('[data-intent]');
 const refresh=()=>{
  const termsEnabled=!intent || intent.value==='negotiating';
  form.querySelectorAll('[data-terms]').forEach(fieldset=>{fieldset.hidden=!termsEnabled;fieldset.disabled=!termsEnabled;});
  form.querySelectorAll('[data-house-field]').forEach(section=>{
   const visible=termsEnabled && kind.value==='lot_house';section.hidden=!visible;
   section.querySelectorAll('input,select').forEach(input=>{input.disabled=!visible;});
  });
 };
 kind?.addEventListener('change',refresh);intent?.addEventListener('change',refresh);refresh();
});
