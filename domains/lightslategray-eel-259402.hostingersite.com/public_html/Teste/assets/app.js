document.querySelectorAll('input[name="cpf"]').forEach(function(input){
  input.addEventListener('input', function(){
    let v=this.value.replace(/\D/g,'').slice(0,11);
    v=v.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
    this.value=v;
  });
});