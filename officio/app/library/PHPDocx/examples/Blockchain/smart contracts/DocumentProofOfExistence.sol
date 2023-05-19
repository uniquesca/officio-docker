pragma solidity ^0.4.18;

contract documentproofofexistence {
    struct Document {
        string value;
        bool exist;
    }
    mapping (string => Document) documents;

    function documentproofofexistence() public {}

    function addDocument(string _digest) public returns (bool) {
        if (documents[_digest].exist) {
            return false;
        }

        documents[_digest].value = _digest;
        documents[_digest].exist = true;

        return true;
    }

    function checkDocumentExists(string _digest) public view returns (bool) {
        if (documents[_digest].exist) {
            return true;
        }

        return false;
    }
}