// Create user extensions namespace (Ext.ux)
Ext.namespace('Ext.ux');

Ext.ux.MoneyField = function(config) {
//    call parent constructor
    Ext.ux.MoneyField.superclass.constructor.call(this, config);

} // end of Ext.ux.IconCombo constructor
 
// extend
Ext.extend(Ext.ux.MoneyField, Ext.form.NumberField, {
    /**
     * @cfg {Boolean} allowBlank Specify false to validate that the value's length is > 0 (defaults to false)
     */
    allowBlank : this.allowBlank !== undefined ? this.allowBlank : false,

    /**
     * @cfg {Boolean} allowDecimals False to disallow decimal values (defaults to true)
     */
    allowDecimals : this.allowDecimals !== undefined ? this.allowDecimals : true,
    
    /**
     * @cfg {String} decimalSeparator Character(s) to allow as the decimal separator (defaults to '.')
     */
    decimalSeparator : this.decimalSeparator !== undefined ? this.decimalSeparator : ".",
    
    /**
     * @cfg {Number} decimalPrecision The maximum precision to display after the decimal separator (defaults to 2)
     */
    decimalPrecision : this.decimalPrecision !== undefined ? this.decimalPrecision : 2,
    
    /**
     * @cfg {Boolean} allowNegative False to prevent entering a negative sign (defaults to false)
     */
    allowNegative : this.allowNegative !== undefined ? this.allowNegative : false,
    
    /**
     * @cfg {Number} minValue The minimum allowed value (defaults to 0.01)
     */
    minValue : this.minValue !== undefined ? this.minValue : 0.01,
    
    /**
     * @cfg {Number} width The width of this component in pixels (defaults to auto)
     */
    width : this.width !== undefined ? this.width : 60
    
}); // end of extend

Ext.reg('moneyfield', Ext.ux.MoneyField);