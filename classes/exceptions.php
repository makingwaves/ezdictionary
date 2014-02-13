<?php
namespace MakingWaves\eZDictionary;

/**
 * DictionaryLogic exception classes
 */
class DictionaryLogicIncorrectOperatorNameException extends \Exception {}
class DictionaryLogicIncorrectOperatorValueException extends \Exception {}
class DictionaryLogicIncorrectOperatorParamException extends \Exception {}
class DictionaryLogicIncorrectAttributeException extends \Exception {}
class DictionaryLogicIncorrectClassNameException extends \Exception {}
class DictionaryLogicIncorrectParameterTypeException extends \Exception {}
class DictionaryLogicParameterDoesNotExistException extends \Exception {}
class DictionaryLogicMissingParentNodesException extends \Exception {}

/**
 * CacheMechanism exception classes
 */
class CacheMechanismIncorrectParentArray extends \Exception {}