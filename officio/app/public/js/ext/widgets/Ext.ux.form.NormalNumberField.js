// create namespace
Ext.ns('Ext.ux.form');

Ext.ux.form.NormalNumberField = Ext.extend(Ext.form.NumberField, {
    /*
     * @cfg {Boolean} true forces the field to show following zeroes. e.g.: 20.00
     */
    forceDecimalPrecision : false

    ,fixPrecision : function(value){
        var nan = isNaN(value);
        if(!this.allowDecimals || this.decimalPrecision == -1 || nan){
            return nan ? '' : value;
        }
        value = parseFloat(value).toFixed(this.decimalPrecision);
        if(this.forceDecimalPrecision)return value;
        return parseFloat(value);
    }
    ,setValue : function(v){
        if(!Ext.isEmpty(v)){
            if(typeof v != 'number'){
                if(this.forceDecimalPrecision){
                    v = parseFloat(String(v).replace(this.decimalSeparator, ".")).toFixed(this.decimalPrecision);
                } else {
                    v = parseFloat(String(v).replace(this.decimalSeparator, "."));
                }
            }
            v = isNaN(v) ? '' : String(v).replace(".", this.decimalSeparator);
        }
        return Ext.form.NumberField.superclass.setValue.call(this, v);
    }
});
 
// register xtype
Ext.reg('normalnumber', Ext.ux.form.NormalNumberField);
