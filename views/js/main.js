window.onload = function(){

    let promise = new Promise(function (resolve,reject){
        let temp = document.createElement("div");
        if((window.location.pathname.includes('/order')) || (window.location.pathname.includes('/ar'))){
            let label = document.querySelectorAll('span')
            console.log(label)

                for( let i = 0 ; i < label.length ; i++){
                    if(label[i].innerText.includes('تمارا: ادفعها') || label[i].innerText.includes('Pay in full') ){
                        swapElements(label[i], label[i].nextElementSibling, temp)
                    }
                    if(label[i].innerText.includes('قسم') || label[i].innerText.includes('Split') ){
                    
                        swapElements(label[i], label[i].nextElementSibling, temp)
                    }
                    if(label[i].innerText.includes('ادفع الشهر الجاي') || label[i].innerText.includes('Pay next month') ){
                        swapElements(label[i], label[i].nextElementSibling, temp)
                    }
                }
    
                function swapElements(obj1, obj2, temp) {
                    console.log('in swap')
                    console.log(obj1)
                    console.log(obj1)
                    if((obj1 !== null) && (obj2 !== null)){
                        // create marker element and insert it where obj1 is
                    obj1.parentNode.insertBefore(temp, obj1);
                
                    // move obj1 to right before obj2
                    obj2.parentNode.insertBefore(obj1, obj2);
                
                    // move obj2 to right before where obj1 used to be
                    temp.parentNode.insertBefore(obj2, temp);
                    }  
                } 
    
            } 
            resolve(temp)
    }).then(function(val){
        console.log(val)
        val.remove();
    })

};

