pragma solidity ^0.4.18;

contract DocumentStore {
    string digest;
    
    function documentstore(string _digest) public {
        digest = _digest;
    }

    function get() public constant returns (string) {
        return digest;
    }
}